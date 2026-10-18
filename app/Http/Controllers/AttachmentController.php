<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\AttachmentLink;
use App\Support\AttachableEntities;
use App\Support\DocumentVersioning;
use App\Support\RelatedFiles;
use App\Support\Uploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function store(Request $request, string $type, string $id): RedirectResponse
    {
        abort_unless(AttachableEntities::find($type, $id), 404);
        abort_unless(AttachableEntities::canEdit(Auth::user(), $type, (string) $request->input('category')), 403);
        $exts = config('foundry.attachments.extensions');
        $max = config('foundry.attachments.max_kb');
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['required', 'file', 'max:'.$max, 'extensions:'.implode(',', $exts)],
            'category' => ['required', Rule::in(array_keys(AttachableEntities::CATEGORIES))],
            'title' => ['nullable', 'string', 'max:200'],
        ], ['files.*.max' => 'Each file may be at most '.Uploads::megabytes($max).'.', 'files.*.uploaded' => Uploads::failedMessage(), 'files.max' => 'Attach at most 10 files at a time.', 'files.*.extensions' => 'That file type is not allowed. Allowed: '.implode(', ', $exts).'.']);

        $saved = 0;
        $error = self::saveFiles($request->file('files'), $type, $id, $request->input('category'), $request->input('title'), $saved);
        if ($error) {
            return back()->withErrors(['files' => $error])->withInput();
        }

        return back()->with('success', $saved === 1 ? 'File attached.' : "{$saved} files attached.");
    }

    /** Stores uploaded files against a record. Returns an error message, or null when all were saved. */
    /** @param array<string, mixed> $extra Extra columns for each new row, such as the version fields. */
    public static function saveFiles(array $files, string $type, string $id, string $category, ?string $title, int &$saved = 0, array $extra = []): ?string
    {
        $disk = config('foundry.attachments.disk');
        foreach ($files as $file) {
            $mime = (string) $file->getMimeType();
            $ext = strtolower($file->getClientOriginalExtension());
            // Content must match the claimed type for the formats we show inline.
            $strict = ['pdf' => ['application/pdf'], 'png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'webp' => ['image/webp']];
            if (isset($strict[$ext]) && ! in_array($mime, $strict[$ext], true)) {
                return "{$file->getClientOriginalName()} is not really a .{$ext} file.";
            }
            $name = Str::limit(preg_replace('/[^\pL\pN\s._()-]+/u', '_', basename($file->getClientOriginalName())), 150, '');
            $path = $file->storeAs('attachments/'.now()->format('Y/m'), (string) Str::uuid().'.'.$ext, $disk);
            Attachment::create([
                'attachable_type' => $type, 'attachable_id' => $id, 'category' => $category, 'title' => $title,
                'original_name' => $name, 'disk' => $disk, 'path' => $path, 'mime_type' => $mime, 'size_bytes' => $file->getSize(),
                'sha256' => hash_file('sha256', $file->getRealPath()), 'uploaded_by_id' => Auth::id(), 'uploaded_by_name' => Auth::user()?->name,
            ] + $extra);
            $saved++;
        }

        return null;
    }

    /** A new version of a file already on the record. The old one stays, marked as replaced. */
    public function version(Request $request, Attachment $attachment): RedirectResponse
    {
        abort_if($attachment->removed_at !== null || $attachment->superseded_at !== null || ! DocumentVersioning::appliesTo($attachment->category), 404);
        abort_unless(AttachableEntities::canEdit(Auth::user(), $attachment->attachable_type, $attachment->category), 403);
        $exts = config('foundry.attachments.extensions');
        $max = config('foundry.attachments.max_kb');
        $request->validate([
            'file' => ['required', 'file', 'max:'.$max, 'extensions:'.implode(',', $exts)],
            'revision_label' => ['nullable', 'string', 'max:30'],
            'change_note' => ['nullable', 'string', 'max:300'],
        ], ['file.max' => 'The file may be at most '.Uploads::megabytes($max).'.', 'file.uploaded' => Uploads::failedMessage(), 'file.extensions' => 'That file type is not allowed. Allowed: '.implode(', ', $exts).'.']);

        $file = $request->file('file');
        if (hash_file('sha256', $file->getRealPath()) === $attachment->sha256) {
            return back()->withErrors(['files' => 'That file is identical to the current version.']);
        }

        $error = DB::transaction(function () use ($request, $attachment, $file) {
            $newId = (string) Str::uuid();
            $saved = 0;
            $error = self::saveFiles([$file], $attachment->attachable_type, $attachment->attachable_id, $attachment->category, $attachment->title, $saved, [
                'id' => $newId, 'document_id' => $attachment->document_id, 'version' => (int) Attachment::where('document_id', $attachment->document_id)->max('version') + 1,
                'revision_label' => $request->input('revision_label'), 'change_note' => $request->input('change_note'),
            ]);
            if ($error === null) {
                $attachment->update(['superseded_at' => now(), 'superseded_by_id' => $newId]);
            }

            return $error;
        });
        if ($error) {
            return back()->withErrors(['files' => $error]);
        }

        return back()->with('success', 'New version added. The earlier version is kept in the history.');
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        abort_if($attachment->removed_at !== null, 404);
        abort_unless(AttachableEntities::canView(Auth::user(), $attachment->attachable_type, $attachment->category) || $this->mayViewThroughLink($attachment), 403);
        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404, 'The file is missing from storage.');

        return $disk->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->isInlineViewable() ? $attachment->mime_type : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox",
        ], $attachment->isInlineViewable() ? 'inline' : 'attachment');
    }

    /** Matching files on other records that this person may link to the record they are on. */
    public function find(Request $request): JsonResponse
    {
        $d = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100'], 'type' => ['required', 'string'], 'id' => ['required', 'string']]);
        abort_unless(AttachableEntities::find($d['type'], $d['id']), 404);
        $like = '%'.mb_strtolower($d['q']).'%';
        $linked = AttachmentLink::where('attachable_type', $d['type'])->where('attachable_id', $d['id'])->pluck('document_id');
        $found = Attachment::current()->whereNotIn('category', AttachableEntities::SENSITIVE_CATEGORIES)
            ->where(fn ($q) => $q->whereRaw('lower(coalesce(title, \'\')) like ?', [$like])->orWhereRaw('lower(original_name) like ?', [$like]))
            ->whereNotIn('document_id', $linked)
            ->where(fn ($q) => $q->where('attachable_type', '!=', $d['type'])->orWhere('attachable_id', '!=', $d['id']))
            ->orderByDesc('created_at')->limit(30)->get()
            ->filter(fn (Attachment $a) => AttachableEntities::canView(Auth::user(), $a->attachable_type, $a->category))->take(10);

        return response()->json($found->map(fn (Attachment $a) => [
            'id' => $a->id, 'name' => $a->title ?: $a->original_name, 'category' => AttachableEntities::CATEGORIES[$a->category] ?? $a->category,
            'record' => AttachableEntities::describe($a->attachable_type, $a->attachable_id),
        ])->values());
    }

    /** Shows an existing file on another record too, without a second copy. */
    public function link(Request $request, Attachment $attachment, string $type, string $id): RedirectResponse
    {
        abort_if($attachment->removed_at !== null || $attachment->superseded_at !== null, 404);
        abort_unless(AttachableEntities::find($type, $id), 404);
        abort_if($attachment->attachable_type === $type && $attachment->attachable_id === $id, 422, 'The file is already on this record.');
        abort_unless(AttachableEntities::canEdit(Auth::user(), $type, $attachment->category) && AttachableEntities::canView(Auth::user(), $attachment->attachable_type, $attachment->category), 403);
        AttachmentLink::firstOrCreate(
            ['document_id' => $attachment->document_id, 'attachable_type' => $type, 'attachable_id' => $id],
            ['linked_by_id' => Auth::id(), 'linked_by_name' => Auth::user()?->name],
        );

        return back()->with('success', 'File linked. It stays with its original record; removing it here only removes the link.');
    }

    public function unlink(AttachmentLink $link): RedirectResponse
    {
        $category = Attachment::where('document_id', $link->document_id)->current()->value('category');
        abort_unless(AttachableEntities::canEdit(Auth::user(), $link->attachable_type, $category), 403);
        $link->delete();

        return back()->with('success', 'Link removed. The file is still on its original record.');
    }

    /** Whether the file is shown, by a link, on a record this person may see it on. */
    private function mayViewThroughLink(Attachment $attachment): bool
    {
        return AttachmentLink::where('document_id', $attachment->document_id)->get()
            ->contains(fn (AttachmentLink $l) => AttachableEntities::canView(Auth::user(), $l->attachable_type, $attachment->category));
    }

    /** Records the document versions this record is made to. */
    public function pin(string $type, string $id): RedirectResponse
    {
        abort_unless(AttachableEntities::find($type, $id), 404);
        abort_unless(AttachableEntities::canEdit(Auth::user(), $type), 403);
        abort_unless(DocumentVersioning::enabled(), 404);
        $count = RelatedFiles::pin($type, $id);

        return back()->with('success', $count === 1 ? 'Recorded the version of 1 document used.' : "Recorded the versions of {$count} documents used.");
    }

    public function cover(Attachment $attachment): RedirectResponse
    {
        abort_if($attachment->removed_at !== null || ! $attachment->isImage(), 404);
        abort_unless(AttachableEntities::canEdit(Auth::user(), $attachment->attachable_type, $attachment->category), 403);
        DB::transaction(function () use ($attachment) {
            Attachment::where('attachable_type', $attachment->attachable_type)->where('attachable_id', $attachment->attachable_id)
                ->where('is_cover', true)->update(['is_cover' => false]);
            $attachment->update(['is_cover' => true]);
        });

        return back()->with('success', 'Main photo set.');
    }

    /** Hidden from the record but kept on disk and in the audit trail. */
    public function remove(Request $request, Attachment $attachment): RedirectResponse
    {
        $d = $request->validate(['removal_reason' => ['required', 'string', 'max:300']]);
        abort_if($attachment->removed_at !== null, 404);
        abort_unless(AttachableEntities::canEdit(Auth::user(), $attachment->attachable_type, $attachment->category), 403);
        DB::transaction(function () use ($attachment, $d) {
            $attachment->update(['removed_at' => now(), 'removed_by_name' => Auth::user()?->name, 'removal_reason' => $d['removal_reason']]);
            if ($attachment->superseded_at === null && ($previous = $attachment->olderVersions()->first())) {
                $previous->update(['superseded_at' => null, 'superseded_by_id' => null]);   // the version before it is current again
            }
        });

        return back()->with('success', 'File removed from the record. A copy is kept for the audit trail.');
    }
}
