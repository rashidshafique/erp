<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moulding_teams', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('code', 30)->unique();
            $t->string('name', 100);
            $t->string('lead_name', 150);
            $t->string('lead_phone', 40)->nullable();
            $t->unsignedSmallInteger('member_count')->default(1);
            $t->string('status', 20)->default('ACTIVE');
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('moulding_stations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('code', 30)->unique();
            $t->string('name', 100);
            $t->string('station_type', 30)->default('FLOOR');
            $t->string('status', 20)->default('ACTIVE');
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('moulding_work_lists', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('list_number', 30)->unique();
            $t->date('list_date');
            $t->string('shift', 30)->default('General');
            $t->string('status', 20)->default('DRAFT');
            $t->string('issued_by', 150)->nullable();
            $t->timestampTz('issued_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('moulding_work_list_items', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('moulding_work_list_id')->constrained('moulding_work_lists')->cascadeOnDelete();
            $t->unsignedSmallInteger('sequence')->default(1);
            $t->foreignUuid('moulding_team_id')->constrained('moulding_teams');
            $t->foreignUuid('moulding_station_id')->constrained('moulding_stations');
            $t->foreignUuid('production_order_id')->constrained('production_orders');
            $t->foreignUuid('pattern_revision_id')->constrained('pattern_revisions');
            $t->string('sand_system', 100)->default('Sheera');
            $t->unsignedInteger('planned_moulds');
            $t->string('status', 20)->default('PLANNED');
            $t->uuid('moulding_run_id')->nullable();
            $t->text('instructions')->nullable();
            $t->timestamps();
        });

        Schema::table('moulding_runs', function (Blueprint $t) {
            $t->uuid('moulding_team_id')->nullable();
            $t->uuid('moulding_station_id')->nullable();
            $t->uuid('moulding_work_list_item_id')->nullable();
        });

        DB::statement("ALTER TABLE moulding_teams ADD CONSTRAINT moulding_teams_status_chk CHECK (status IN ('ACTIVE','INACTIVE'))");
        DB::statement("ALTER TABLE moulding_stations ADD CONSTRAINT moulding_stations_status_chk CHECK (status IN ('ACTIVE','INACTIVE','MAINTENANCE'))");
        DB::statement("ALTER TABLE moulding_work_lists ADD CONSTRAINT moulding_work_lists_status_chk CHECK (status IN ('DRAFT','ISSUED','COMPLETED','CANCELLED'))");
        DB::statement("ALTER TABLE moulding_work_list_items ADD CONSTRAINT moulding_work_list_items_status_chk CHECK (status IN ('PLANNED','IN_PROGRESS','DONE','SKIPPED'))");
        DB::statement('ALTER TABLE moulding_work_list_items ADD CONSTRAINT moulding_work_list_items_qty_chk CHECK (planned_moulds > 0)');
    }

    public function down(): void
    {
        Schema::table('moulding_runs', fn (Blueprint $t) => $t->dropColumn(['moulding_team_id', 'moulding_station_id', 'moulding_work_list_item_id']));
        Schema::dropIfExists('moulding_work_list_items');
        Schema::dropIfExists('moulding_work_lists');
        Schema::dropIfExists('moulding_stations');
        Schema::dropIfExists('moulding_teams');
    }
};
