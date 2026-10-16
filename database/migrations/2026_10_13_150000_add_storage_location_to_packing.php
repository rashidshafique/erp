<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packing_records', fn (Blueprint $t) => $t->string('storage_location', 60)->nullable());
    }

    public function down(): void
    {
        Schema::table('packing_records', fn (Blueprint $t) => $t->dropColumn('storage_location'));
    }
};
