<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** A tool row links to the register record it made or changed, and to the old tool it replaces; the development can name the core box it replaces or modifies. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pattern_development_tools', function (Blueprint $table) {
            $table->foreignUuid('core_box_id')->nullable()->after('pattern_id')->constrained('core_boxes')->nullOnDelete();
            $table->foreignUuid('core_definition_id')->nullable()->after('core_box_id')->constrained('core_definitions')->nullOnDelete();
            $table->foreignUuid('replaces_pattern_id')->nullable()->after('core_definition_id')->constrained('patterns')->nullOnDelete();
            $table->foreignUuid('replaces_core_box_id')->nullable()->after('replaces_pattern_id')->constrained('core_boxes')->nullOnDelete();
            $table->timestamp('retired_at')->nullable()->after('status_note');
        });
        Schema::table('pattern_developments', function (Blueprint $table) {
            $table->foreignUuid('core_box_id')->nullable()->after('pattern_id')->constrained('core_boxes')->nullOnDelete();
        });

        // A replacement's tool is the new one: the old pattern it was opened for moves to "replaces".
        DB::table('pattern_development_tools')->whereIn('pattern_development_id', DB::table('pattern_developments')->where('type', 'REPLACEMENT')->select('id'))
            ->whereNotNull('pattern_id')->update(['replaces_pattern_id' => DB::raw('pattern_id'), 'pattern_id' => null]);
    }

    public function down(): void
    {
        DB::table('pattern_development_tools')->whereNotNull('replaces_pattern_id')->whereNull('pattern_id')->update(['pattern_id' => DB::raw('replaces_pattern_id')]);
        Schema::table('pattern_developments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('core_box_id');
        });
        Schema::table('pattern_development_tools', function (Blueprint $table) {
            $table->dropConstrainedForeignId('core_box_id');
            $table->dropConstrainedForeignId('core_definition_id');
            $table->dropConstrainedForeignId('replaces_pattern_id');
            $table->dropConstrainedForeignId('replaces_core_box_id');
            $table->dropColumn('retired_at');
        });
    }
};
