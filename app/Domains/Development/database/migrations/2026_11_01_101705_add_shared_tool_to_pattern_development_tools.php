<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A tool row can stand for a tool made on another development (design 18c): it shows that tool's status and carries no cost. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pattern_development_tools', function (Blueprint $table) {
            $table->foreignUuid('shared_tool_id')->nullable()->after('pattern_development_id')->constrained('pattern_development_tools')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pattern_development_tools', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shared_tool_id');
        });
    }
};
