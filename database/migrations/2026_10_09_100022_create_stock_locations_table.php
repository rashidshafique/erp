
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->string('location_type', 40);
            $table->uuid('parent_location_id')->nullable();

            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();

            $table->timestamps();

            $table->index('parent_location_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_locations');
    }
};