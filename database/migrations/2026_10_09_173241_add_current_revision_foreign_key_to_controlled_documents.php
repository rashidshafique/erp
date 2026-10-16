
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('controlled_documents', function (Blueprint $table) {
            $table->foreign('current_revision_id')
                ->references('id')
                ->on('controlled_document_revisions')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('controlled_documents', function (Blueprint $table) {
            $table->dropForeign(['current_revision_id']);
        });
    }
};