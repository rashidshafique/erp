<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The team comes from the moulding line; a line with no team set still takes work.
        DB::statement('ALTER TABLE moulding_work_list_items ALTER COLUMN moulding_team_id DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DELETE FROM moulding_work_list_items WHERE moulding_team_id IS NULL');
        DB::statement('ALTER TABLE moulding_work_list_items ALTER COLUMN moulding_team_id SET NOT NULL');
    }
};
