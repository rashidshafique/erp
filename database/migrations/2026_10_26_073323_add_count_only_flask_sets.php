<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Count-only flasks: a size can be kept as "we have 40 sets" instead of a register of each flask. */
    public function up(): void
    {
        Schema::table('flask_sizes', function (Blueprint $t) {
            $t->unsignedInteger('sets_owned')->nullable();   // used only while no flask of the size is registered
        });
        Schema::table('mould_assemblies', function (Blueprint $t) {
            $t->boolean('holds_flask_set')->default(false);  // a counted set of its flask size is in this mould
        });
        Schema::table('moulding_runs', function (Blueprint $t) {
            $t->unsignedInteger('flask_sets_held')->default(0);   // counted sets a run holds under the "runs claim by count" rule
            $t->foreignUuid('flask_size_id')->nullable()->constrained('flask_sizes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('moulding_runs', function (Blueprint $t) {
            $t->dropConstrainedForeignId('flask_size_id');
            $t->dropColumn('flask_sets_held');
        });
        Schema::table('mould_assemblies', fn (Blueprint $t) => $t->dropColumn('holds_flask_set'));
        Schema::table('flask_sizes', fn (Blueprint $t) => $t->dropColumn('sets_owned'));
    }
};
