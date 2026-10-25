<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_box_cavities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('core_box_id')->constrained('core_boxes')->cascadeOnDelete();
            $table->unsignedInteger('cavity_number');
            $table->foreignUuid('core_definition_id')->constrained('core_definitions')->restrictOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['core_box_id', 'cavity_number']);
        });

        Schema::table('core_boxes', function (Blueprint $table) {
            $table->dropUnique(['core_definition_id', 'revision']);
            $table->string('origin', 20)->default('NEW')->after('revision');
            $table->foreignUuid('modified_from_core_box_id')->nullable()->after('origin')->constrained('core_boxes')->nullOnDelete();
            $table->foreignUuid('modified_from_pattern_id')->nullable()->after('modified_from_core_box_id')->constrained('patterns')->nullOnDelete();
        });

        // A box that made one core in every cavity keeps doing so: one row per cavity, all for its core.
        DB::table('core_boxes')->orderBy('id')->each(function ($box) {
            $now = now();
            $rows = [];
            for ($n = 1; $n <= max(1, (int) $box->cavities); $n++) {
                $rows[] = ['id' => (string) \Illuminate\Support\Str::uuid(), 'core_box_id' => $box->id, 'cavity_number' => $n, 'core_definition_id' => $box->core_definition_id, 'created_at' => $now, 'updated_at' => $now];
            }
            DB::table('core_box_cavities')->insert($rows);
        });
    }

    public function down(): void
    {
        Schema::table('core_boxes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('modified_from_pattern_id');
            $table->dropConstrainedForeignId('modified_from_core_box_id');
            $table->dropColumn('origin');
            $table->unique(['core_definition_id', 'revision']);
        });
        Schema::dropIfExists('core_box_cavities');
    }
};
