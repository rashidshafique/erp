
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packing_record_items', function (Blueprint $table) {
            $table->string('uom', 10)->default('each');
            $table->decimal('quantity_packed', 14, 3)->change();
        });

        Schema::table('dispatch_items', function (Blueprint $table) {
            $table->string('uom', 10)->default('each');
            $table->decimal('quantity_dispatched', 14, 3)->change();
        });
    }

    public function down(): void
    {
        Schema::table('dispatch_items', function (Blueprint $table) {
            $table->dropColumn('uom');
            $table->unsignedInteger('quantity_dispatched')->change();
        });

        Schema::table('packing_record_items', function (Blueprint $table) {
            $table->dropColumn('uom');
            $table->unsignedInteger('quantity_packed')->change();
        });
    }
};