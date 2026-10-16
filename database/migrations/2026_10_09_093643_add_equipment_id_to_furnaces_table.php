
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('furnaces', function (Blueprint $table) {
            $table->foreignUuid('equipment_id')
                ->nullable()
                ->unique()
                ->constrained('equipment')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('furnaces', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipment_id');
        });
    }
};