<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TOOLS = ['patterns', 'core_boxes', 'core_definitions', 'mould_configurations'];

    public function up(): void
    {
        Schema::create('code_sequences', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->unsignedBigInteger('next_value')->default(1);
        });

        // One register for every engraved mark, so no two tools in the foundry ever carry the same one.
        Schema::create('tool_marks', function (Blueprint $t) {
            $t->id();
            $t->string('mark_code', 30)->unique();
            $t->string('tool_type', 30);
            $t->string('tool_id', 64);
            $t->timestamps();
            $t->unique(['tool_type', 'tool_id']);
        });

        foreach (self::TOOLS as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('mark_code', 30)->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TOOLS as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('mark_code'));
        }
        Schema::dropIfExists('tool_marks');
        Schema::dropIfExists('code_sequences');
    }
};
