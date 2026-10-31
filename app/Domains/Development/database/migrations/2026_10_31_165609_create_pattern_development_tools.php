<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** The tools a development makes or changes (a pattern, core boxes, gauges…), each with its own maker, cost and status. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pattern_development_tools', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pattern_development_id')->constrained('pattern_developments')->cascadeOnDelete();
            $table->string('kind', 12)->default('PATTERN');
            $table->string('label', 200);
            $table->string('route', 8)->default('NEW');
            $table->string('made_by', 20)->default('DECIDE_LATER');
            $table->string('maker', 150)->nullable();
            $table->string('who_pays', 10)->nullable();
            $table->decimal('quoted_cost', 14, 2)->nullable();
            $table->decimal('actual_cost', 14, 2)->nullable();
            $table->date('due_on')->nullable();
            $table->string('status', 14)->default('NOT_STARTED');
            $table->string('status_note', 300)->nullable();
            $table->foreignUuid('pattern_id')->nullable()->constrained('patterns')->nullOnDelete();
            $table->timestamps();
            $table->index('pattern_development_id');
        });

        $routes = ['NEW_PART' => 'NEW', 'NEW_PRODUCT' => 'NEW', 'REPLACEMENT' => 'NEW', 'MODIFICATION' => 'MODIFY', 'CUSTOMER_PATTERN' => 'EXISTING'];
        $now = now();
        DB::table('pattern_developments')->orderBy('created_at')->each(function ($d) use ($routes, $now) {
            $pattern = $d->pattern_id ? DB::table('patterns')->where('id', $d->pattern_id)->first() : null;
            DB::table('pattern_development_tools')->insert([
                'id' => (string) Str::uuid(), 'pattern_development_id' => $d->id, 'kind' => 'PATTERN',
                'label' => $pattern ? trim($pattern->code.' '.$pattern->name) : 'Pattern', 'route' => $routes[$d->type] ?? 'NEW',
                'made_by' => $d->pattern_by, 'maker' => $d->pattern_maker, 'who_pays' => $d->who_pays,
                'quoted_cost' => $d->quoted_tooling_cost, 'actual_cost' => $d->actual_cost, 'due_on' => $d->due_on,
                'status' => $d->status === 'DONE' ? 'TRIAL_PASSED' : 'NOT_STARTED', 'pattern_id' => $d->pattern_id,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pattern_development_tools');
    }
};
