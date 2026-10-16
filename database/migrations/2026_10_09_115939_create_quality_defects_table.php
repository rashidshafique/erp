
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_defects', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->string('category', 50)->default('VISUAL');
            $table->string('default_severity', 20)->default('MINOR');

            $table->text('description')->nullable();
            $table->text('inspection_guidance')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_defects');
    }
};