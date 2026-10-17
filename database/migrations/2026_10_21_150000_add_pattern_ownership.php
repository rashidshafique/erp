<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Who owns a pattern and where it is. A pattern is the foundry's, a customer's (held in the foundry's custody), or being
 * paid off by a customer (theirs once the amortised quantity is dispatched or it is paid). Custody says where it physically
 * is: in store, at the pattern shop, lent to another supplier, returned to the customer, or scrapped. Every change of owner
 * or custody is an ownership event. Scrapping a customer's pattern goes through an approval, active from the start.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patterns', function (Blueprint $table) {
            $table->string('owner_type', 20)->default('FOUNDRY');
            $table->foreignUuid('owner_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->boolean('supplied_by_customer')->default(false);
            $table->foreignUuid('ownership_quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->string('ownership_reference', 100)->nullable();
            $table->date('acquired_on')->nullable();
            $table->decimal('insured_value', 14, 2)->nullable();
            $table->unsignedInteger('amortise_qty')->nullable();
            $table->text('ownership_notes')->nullable();
            $table->string('custody', 20)->default('IN_STORE');
            $table->string('custody_location', 150)->nullable();
        });
        DB::statement("ALTER TABLE patterns ADD CONSTRAINT patterns_owner_valid CHECK (owner_type IN ('FOUNDRY','CUSTOMER','PAYING_OFF') AND (owner_type = 'FOUNDRY' OR owner_customer_id IS NOT NULL))");
        DB::statement("ALTER TABLE patterns ADD CONSTRAINT patterns_custody_valid CHECK (custody IN ('IN_STORE','PATTERN_SHOP','LENT','RETURNED','SCRAPPED'))");

        Schema::create('pattern_ownership_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pattern_id')->constrained('patterns')->cascadeOnDelete();
            $table->string('event_type', 30);
            $table->string('from_value', 150)->nullable();
            $table->string('to_value', 150)->nullable();
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->date('occurred_on');
            $table->string('recorded_by', 150)->nullable();
            $table->timestamps();
            $table->index(['pattern_id', 'occurred_on']);
        });

        if (! DB::table('approval_policies')->where('action_key', 'pattern.scrap_customer_pattern')->exists()) {
            DB::table('approval_policies')->insert([
                'id' => (string) Str::uuid(), 'action_key' => 'pattern.scrap_customer_pattern', 'is_active' => true,
                'permission' => 'approvals.decide', 'required_approvals' => 1, 'allow_self_approval' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('approval_policies')->where('action_key', 'pattern.scrap_customer_pattern')->delete();
        Schema::dropIfExists('pattern_ownership_events');
        DB::statement('ALTER TABLE patterns DROP CONSTRAINT IF EXISTS patterns_owner_valid');
        DB::statement('ALTER TABLE patterns DROP CONSTRAINT IF EXISTS patterns_custody_valid');
        Schema::table('patterns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_customer_id');
            $table->dropConstrainedForeignId('ownership_quotation_id');
            $table->dropColumn(['owner_type', 'supplied_by_customer', 'ownership_reference', 'acquired_on', 'insured_value', 'amortise_qty', 'ownership_notes', 'custody', 'custody_location']);
        });
    }
};
