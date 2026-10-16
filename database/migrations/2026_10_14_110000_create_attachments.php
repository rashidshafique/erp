<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('attachable_type', 40);
            $t->string('attachable_id', 64);
            $t->string('category', 20)->default('OTHER');
            $t->string('title', 200)->nullable();
            $t->string('original_name', 200);
            $t->string('disk', 30);
            $t->string('path', 300);
            $t->string('mime_type', 120)->nullable();
            $t->unsignedBigInteger('size_bytes');
            $t->string('sha256', 64);
            $t->unsignedBigInteger('uploaded_by_id')->nullable();
            $t->string('uploaded_by_name', 150)->nullable();
            $t->timestampTz('removed_at')->nullable();
            $t->string('removed_by_name', 150)->nullable();
            $t->string('removal_reason', 300)->nullable();
            $t->timestamps();
            $t->index(['attachable_type', 'attachable_id']);
            $t->index('sha256');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
