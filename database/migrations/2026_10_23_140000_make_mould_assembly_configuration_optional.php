<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // A flaskless mould is one block with no cope-and-drag configuration to pick.
        DB::statement('ALTER TABLE mould_assemblies ALTER COLUMN mould_configuration_id DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE mould_assemblies ALTER COLUMN mould_configuration_id SET NOT NULL');
    }
};
