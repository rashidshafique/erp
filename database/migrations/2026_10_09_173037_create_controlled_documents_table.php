
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('controlled_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('document_number', 100)->unique();
            $table->string('title', 250);
            $table->string('document_type', 50);
            $table->string('status', 30)->default('DRAFT');

            $table->string('owning_department', 100)->nullable();
            $table->string('owner_name', 150)->nullable();

            $table->string('language_code', 10)->default('en');
            $table->string('default_access_level', 30)->default('INTERNAL');

            $table->text('description')->nullable();
            $table->text('keywords')->nullable();

            $table->timestampTz('created_for_review_at')->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->timestampTz('obsolete_at')->nullable();

            $table->foreignUuid('current_revision_id')
                ->nullable()
                ->unique();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['document_type', 'status']);
            $table->index(['owning_department', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('controlled_documents');
    }
};