<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Maintenance photos move into the shared attachments, and off the public disk onto the private one.
 * Each public copy is deleted only after its private copy is written and checked. The old table stays
 * for one release so the move can be checked, and is dropped later. Safe to run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tooling_maintenance_attachments')) {
            return;
        }
        $targetDisk = config('foundry.attachments.disk');

        DB::table('tooling_maintenance_attachments')->orderBy('created_at')->each(function (object $row) use ($targetDisk): void {
            if (DB::table('attachments')->where('id', $row->id)->exists()) {
                return;
            }
            $source = Storage::disk($row->disk);
            if (! $source->exists($row->path)) {
                Log::warning('Tooling maintenance photo missing, not moved', ['id' => $row->id, 'path' => $row->path]);

                return;
            }
            $contents = $source->get($row->path);
            $sha256 = hash('sha256', $contents);
            $extension = strtolower(pathinfo($row->path, PATHINFO_EXTENSION)) ?: 'jpg';
            $path = 'attachments/'.Carbon::parse($row->created_at)->format('Y/m').'/'.Str::uuid().'.'.$extension;
            $target = Storage::disk($targetDisk);
            $target->put($path, $contents);
            if (hash('sha256', (string) $target->get($path)) !== $sha256) {
                throw new RuntimeException("Copy of tooling maintenance photo {$row->id} does not match the original.");
            }

            DB::table('attachments')->insert([
                'id' => $row->id, 'attachable_type' => 'tooling_maintenance', 'attachable_id' => $row->tooling_maintenance_record_id,
                'category' => 'PHOTO', 'title' => $row->caption ? Str::limit($row->caption, 200, '') : null,
                'original_name' => Str::limit($row->original_filename ?: basename($row->path), 200, ''),
                'disk' => $targetDisk, 'path' => $path, 'mime_type' => $row->mime_type, 'size_bytes' => strlen($contents), 'sha256' => $sha256,
                'uploaded_by_name' => $row->uploaded_by ? Str::limit($row->uploaded_by, 150, '') : null, 'is_cover' => false,
                'created_at' => $row->created_at, 'updated_at' => $row->updated_at,
            ]);
            $source->delete($row->path);
        });
    }

    /** The public copies are gone, so the moved photos stay where they are. */
    public function down(): void {}
};
