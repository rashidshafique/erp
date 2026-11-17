<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A quotation keeps one number for its whole life and its revisions sit inside it (design 30). Until now each version was its own
     * row with its own number, so every enquiry's versions take the number of its first one (old numbers stay findable through
     * number_history). A revision says why it exists and what changed; a price under the margin floor waits for an approver who may
     * attach a condition; closing records the customer's order, or why it was lost; the log keeps the emails, approvals and notes.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE quotations DROP CONSTRAINT quotations_status_valid');
        DB::statement("ALTER TABLE quotations ADD CONSTRAINT quotations_status_valid CHECK (status IN ('DRAFT','AWAITING_APPROVAL','ISSUED','ACCEPTED','REJECTED','EXPIRED','SUPERSEDED'))");

        Schema::table('quotations', function (Blueprint $t) {
            $t->string('status', 20)->default('DRAFT')->change();
            $t->string('revision_reason', 20)->nullable();
            $t->text('change_note')->nullable();
            $t->text('customer_note')->nullable();
            $t->timestampTz('approval_asked_at')->nullable();
            $t->string('approval_asked_by', 150)->nullable();
            $t->string('approved_by', 150)->nullable();
            $t->timestampTz('approved_at')->nullable();
            $t->text('approval_condition')->nullable();
            $t->string('outcome', 10)->nullable();
            $t->string('lost_reason', 20)->nullable();
            $t->decimal('price_gap_pct', 5, 2)->nullable();
            $t->string('customer_po', 100)->nullable();
            $t->date('customer_po_date')->nullable();
        });

        Schema::create('quotation_events', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $t->string('kind', 10);
            $t->string('direction', 3)->nullable();
            $t->text('text');
            $t->string('user_name', 150)->nullable();
            $t->date('happened_on');
            $t->timestamps();
            $t->index(['quotation_id', 'happened_on']);
        });

        DB::statement('ALTER TABLE quotations DROP CONSTRAINT quotations_code_unique');
        $this->shareNumbers();
        DB::statement('ALTER TABLE quotations ADD CONSTRAINT quotations_code_version_unique UNIQUE (code, version)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE quotations DROP CONSTRAINT quotations_code_version_unique');
        $this->restoreNumbers();
        DB::statement('ALTER TABLE quotations ADD CONSTRAINT quotations_code_unique UNIQUE (code)');
        Schema::dropIfExists('quotation_events');
        Schema::table('quotations', function (Blueprint $t) {
            $t->dropColumn(['revision_reason', 'change_note', 'customer_note', 'approval_asked_at', 'approval_asked_by', 'approved_by', 'approved_at', 'approval_condition', 'outcome', 'lost_reason', 'price_gap_pct', 'customer_po', 'customer_po_date']);
        });
        DB::statement("UPDATE quotations SET status = 'DRAFT' WHERE status = 'AWAITING_APPROVAL'");
        Schema::table('quotations', fn (Blueprint $t) => $t->string('status', 12)->default('DRAFT')->change());
        DB::statement('ALTER TABLE quotations DROP CONSTRAINT quotations_status_valid');
        DB::statement("ALTER TABLE quotations ADD CONSTRAINT quotations_status_valid CHECK (status IN ('DRAFT','ISSUED','ACCEPTED','REJECTED','EXPIRED','SUPERSEDED'))");
    }

    /** Every later version of an enquiry's quotation takes the number of its first, and its old number goes into number_history. */
    private function shareNumbers(): void
    {
        $now = now();
        foreach (DB::table('quotations')->orderBy('enquiry_id')->orderBy('version')->get(['id', 'enquiry_id', 'code', 'version'])->groupBy('enquiry_id') as $chain) {
            $first = $chain->first();
            foreach ($chain->skip(1) as $row) {
                DB::table('number_history')->insert(['table_name' => 'quotations', 'column_name' => 'code', 'record_id' => $row->id, 'old_number' => $row->code, 'new_number' => $first->code, 'created_at' => $now]);
                DB::table('quotations')->where('id', $row->id)->update(['code' => $first->code]);
            }
        }
    }

    /** Puts each later version's own number back from the history written above (the earlier renumbering's rows no longer match the code). */
    private function restoreNumbers(): void
    {
        foreach (DB::table('number_history')->where('table_name', 'quotations')->where('column_name', 'code')->get() as $h) {
            $row = DB::table('quotations')->where('id', $h->record_id)->first(['code', 'version']);
            if (! $row || $row->version < 2 || $row->code !== $h->new_number) {
                continue;
            }
            DB::table('quotations')->where('id', $h->record_id)->update(['code' => $h->old_number]);
            DB::table('number_history')->where('id', $h->id)->delete();
        }
    }
};
