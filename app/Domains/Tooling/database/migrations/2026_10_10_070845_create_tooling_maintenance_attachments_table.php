<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tooling_maintenance_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('tooling_maintenance_record_id')
                ->constrained('tooling_maintenance_records')
                ->cascadeOnDelete();

            $table->string('disk', 30)->default('public');
            $table->string('path');
            $table->string('original_filename')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('caption', 500)->nullable();
            $table->string('uploaded_by')->nullable();
            $table->timestamps();

            $table->index([
                'tooling_maintenance_record_id',
                'created_at',
            ], 'tooling_attachment_record_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tooling_maintenance_attachments');
    }
};
