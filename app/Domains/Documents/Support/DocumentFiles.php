<?php

namespace App\Domains\Documents\Support;

use App\Domains\Documents\Models\ControlledDocument;
use App\Domains\Documents\Models\ControlledDocumentRevision;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The file of a revision, kept on the private attachment disk and reachable only through the app. */
class DocumentFiles
{
    /**
     * Stores the upload and returns the revision columns that describe it.
     *
     * @return array<string, mixed>
     */
    public static function store(UploadedFile $file, ControlledDocument $document, ControlledDocumentRevision $revision): array
    {
        $mime = (string) $file->getMimeType();
        $ext = strtolower($file->getClientOriginalExtension());
        $strict = ['pdf' => ['application/pdf'], 'png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'webp' => ['image/webp']];
        if (isset($strict[$ext]) && ! in_array($mime, $strict[$ext], true)) {
            throw ValidationException::withMessages(['file' => "{$file->getClientOriginalName()} is not really a .{$ext} file."]);
        }
        $disk = (string) config('foundry.attachments.disk');
        $path = $file->storeAs('documents/'.$document->id, $revision->id.'.'.$ext, $disk);

        return [
            'original_filename' => mb_substr(basename($file->getClientOriginalName()), 0, 255), 'storage_disk' => $disk, 'storage_path' => $path,
            'mime_type' => $mime, 'file_size_bytes' => $file->getSize(), 'file_sha256' => hash_file('sha256', $file->getRealPath()),
        ];
    }

    public static function delete(ControlledDocumentRevision $revision): void
    {
        if ($revision->hasFile()) {
            Storage::disk($revision->storage_disk)->delete($revision->storage_path);
        }
    }

    public static function response(ControlledDocumentRevision $revision): StreamedResponse
    {
        $disk = Storage::disk($revision->storage_disk);
        abort_unless($revision->hasFile() && $disk->exists($revision->storage_path), 404, 'The file is missing from storage.');
        $inline = in_array($revision->mime_type, ['application/pdf', 'image/png', 'image/jpeg', 'image/webp'], true);

        return $disk->response($revision->storage_path, $revision->original_filename, [
            'Content-Type' => $inline ? $revision->mime_type : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox",
        ], $inline ? 'inline' : 'attachment');
    }
}
