<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A document shown on another record besides the one it was uploaded to. Follows the document, so it always shows the current version.
        Schema::create('attachment_links', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('document_id', 36);
            $t->string('attachable_type', 40);
            $t->string('attachable_id', 64);
            $t->unsignedBigInteger('linked_by_id')->nullable();
            $t->string('linked_by_name', 150)->nullable();
            $t->timestamps();
            $t->unique(['document_id', 'attachable_type', 'attachable_id']);
            $t->index(['attachable_type', 'attachable_id']);
        });

        // The exact version of a document a record was made to, so a later version does not rewrite history.
        Schema::create('attachment_pins', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('pinnable_type', 40);
            $t->string('pinnable_id', 64);
            $t->string('document_id', 36);
            $t->string('attachment_id', 36);
            $t->string('pinned_by_name', 150)->nullable();
            $t->timestamps();
            $t->unique(['pinnable_type', 'pinnable_id', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachment_pins');
        Schema::dropIfExists('attachment_links');
    }
};
