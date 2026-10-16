<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->timestampTz('occurred_at', 6);
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('user_name', 150)->nullable();
            $t->string('action', 20);
            $t->string('entity_type', 120);
            $t->string('entity_id', 64)->nullable();
            $t->string('entity_label', 200)->nullable();
            $t->json('changes')->nullable();           // text kept exactly as hashed
            $t->text('reason')->nullable();
            $t->uuid('batch_id')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->string('route_name', 150)->nullable();
            $t->string('prev_hash', 64);
            $t->string('hash', 64);
            $t->index(['entity_type', 'entity_id', 'id']);
            $t->index('user_id');
            $t->index('occurred_at');
            $t->index('batch_id');
        });

        // Append-only: rows can be added, never changed, removed or truncated.
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION audit_logs_block_change() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'audit_logs is append-only';
END;
$$ LANGUAGE plpgsql;
SQL);
        DB::unprepared('CREATE TRIGGER audit_logs_no_update BEFORE UPDATE OR DELETE ON audit_logs FOR EACH ROW EXECUTE FUNCTION audit_logs_block_change()');
        DB::unprepared('CREATE TRIGGER audit_logs_no_truncate BEFORE TRUNCATE ON audit_logs FOR EACH STATEMENT EXECUTE FUNCTION audit_logs_block_change()');
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update ON audit_logs');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_truncate ON audit_logs');
        Schema::dropIfExists('audit_logs');
        DB::unprepared('DROP FUNCTION IF EXISTS audit_logs_block_change()');
    }
};
