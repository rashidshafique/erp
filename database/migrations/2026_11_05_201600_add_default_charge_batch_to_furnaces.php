<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('furnaces', function (Blueprint $table) {
            $table->foreignUuid('default_charge_batch_id')->nullable()->constrained('charge_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('furnaces', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_charge_batch_id');
        });
    }
};
