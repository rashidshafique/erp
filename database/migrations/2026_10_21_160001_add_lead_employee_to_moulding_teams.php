<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('moulding_teams', function (Blueprint $table) {
            $table->foreignUuid('lead_employee_id')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('moulding_teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lead_employee_id');
        });
    }
};
