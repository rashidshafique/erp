<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the enquiry record (design 14b) and closing it (14c) keep: an owner, the tooling and pattern decision, why it closed, the
 * winning price when lost, a follow-up date, the questions put to the customer and a log of what was exchanged. Adds Declined
 * (we chose not to quote) beside Won, Lost and Cancelled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('pattern_route', 10)->nullable();
            $table->foreignUuid('pattern_id')->nullable()->constrained('patterns')->nullOnDelete();
            $table->string('close_reason', 20)->nullable();
            $table->decimal('winning_price', 14, 2)->nullable();
            $table->date('follow_up_on')->nullable();
            $table->timestampTz('follow_up_sent_at')->nullable();
        });
        DB::statement('ALTER TABLE enquiries DROP CONSTRAINT enquiries_status_valid');
        DB::statement("ALTER TABLE enquiries ADD CONSTRAINT enquiries_status_valid CHECK (status IN ('NEW','FEASIBILITY','QUOTED','WON','LOST','DECLINED','CANCELLED'))");
        DB::statement("ALTER TABLE enquiries ADD CONSTRAINT enquiries_pattern_route_valid CHECK (pattern_route IS NULL OR pattern_route IN ('EXISTING','NEW','CUSTOMER'))");
        DB::statement('ALTER TABLE enquiries ALTER COLUMN lost_reason TYPE text');

        Schema::create('enquiry_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('enquiry_id')->constrained('enquiries')->cascadeOnDelete();
            $table->text('question');
            $table->date('asked_on');
            $table->text('answer')->nullable();
            $table->date('answered_on')->nullable();
            $table->string('asked_by_name', 150)->nullable();
            $table->timestamps();
            $table->index('enquiry_id');
        });

        Schema::create('enquiry_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('enquiry_id')->constrained('enquiries')->cascadeOnDelete();
            $table->string('kind', 10);
            $table->string('direction', 3)->nullable();
            $table->text('text');
            $table->string('contact', 150)->nullable();
            $table->string('user_name', 150)->nullable();
            $table->date('happened_on');
            $table->timestamps();
            $table->index(['enquiry_id', 'happened_on']);
        });
        DB::statement("ALTER TABLE enquiry_events ADD CONSTRAINT enquiry_events_kind_valid CHECK (kind IN ('EMAIL','CALL','MEETING','NOTE','SYSTEM'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiry_events');
        Schema::dropIfExists('enquiry_questions');
        DB::statement('ALTER TABLE enquiries DROP CONSTRAINT IF EXISTS enquiries_pattern_route_valid');
        DB::statement("UPDATE enquiries SET status = 'CANCELLED' WHERE status = 'DECLINED'");
        DB::statement('ALTER TABLE enquiries DROP CONSTRAINT enquiries_status_valid');
        DB::statement("ALTER TABLE enquiries ADD CONSTRAINT enquiries_status_valid CHECK (status IN ('NEW','FEASIBILITY','QUOTED','WON','LOST','CANCELLED'))");
        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
            $table->dropConstrainedForeignId('pattern_id');
            $table->dropColumn(['pattern_route', 'close_reason', 'winning_price', 'follow_up_on', 'follow_up_sent_at']);
        });
    }
};
