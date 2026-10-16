<?php

namespace App\Http\Controllers;

use App\Models\ToolingMaintenanceAttachment;
use App\Models\ToolingMaintenanceRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ToolingMaintenanceAttachmentController extends Controller
{
    public function store(
        Request $request,
        ToolingMaintenanceRecord $toolingMaintenance
    ): RedirectResponse {
        $validated = $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:10'],
            'photos.*' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],
            'captions' => ['nullable', 'array'],
            'captions.*' => ['nullable', 'string', 'max:500'],
            'uploaded_by' => ['nullable', 'string', 'max:255'],
        ]);

        $diskName = 'public';
        $disk = Storage::disk($diskName);
        $savedPaths = [];

        try {
            DB::transaction(function () use (
                $request,
                $toolingMaintenance,
                $diskName,
                $disk,
                &$savedPaths
            ): void {
                foreach ($request->file('photos', []) as $index => $photo) {
                    $path = $photo->store(
                        'tooling-maintenance/'.$toolingMaintenance->id,
                        $diskName
                    );

                    if (!$path) {
                        throw new RuntimeException(
                            'The photo could not be saved.'
                        );
                    }

                    $savedPaths[] = $path;

                    $toolingMaintenance->attachments()->create([
                        'disk' => $diskName,
                        'path' => $path,
                        'original_filename' => $photo->getClientOriginalName(),
                        'mime_type' => $photo->getMimeType(),
                        'size_bytes' => $photo->getSize(),
                        'caption' => $request->input("captions.$index"),
                        'uploaded_by' => $request->input('uploaded_by'),
                    ]);
                }
            });
        } catch (Throwable $exception) {
            foreach ($savedPaths as $path) {
                try {
                    $disk->delete($path);
                } catch (Throwable) {
                    // Preserve the original exception if cleanup also fails.
                }
            }

            throw $exception;
        }

        return redirect()
            ->route('tooling-maintenance.show', $toolingMaintenance)
            ->with('success', count($savedPaths).' photo(s) uploaded.');
    }

    public function destroy(
        Request $request,
        ToolingMaintenanceRecord $toolingMaintenance,
        ToolingMaintenanceAttachment $attachment
    ): RedirectResponse {
        abort_unless(
            $attachment->tooling_maintenance_record_id === $toolingMaintenance->id,
            404
        );

        $disk = Storage::disk($attachment->disk);

        try {
            // A missing file can be cleaned up by removing its stale DB row.
            if (
                $disk->exists($attachment->path)
                && !$disk->delete($attachment->path)
            ) {
                return back()->withErrors([
                    'attachment' => 'The photo file could not be deleted. The record was retained.',
                ]);
            }
        } catch (Throwable) {
            return back()->withErrors([
                'attachment' => 'Storage failed while deleting the photo. The record was retained.',
            ]);
        }

        $attachment->delete();

        return redirect()
            ->route('tooling-maintenance.show', $toolingMaintenance)
            ->with('success', 'Photo deleted.');
    }
}