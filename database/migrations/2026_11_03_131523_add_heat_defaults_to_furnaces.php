<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('furnaces', function (Blueprint $table) {
            $table->boolean('is_default')->default(false);
            $table->foreignUuid('default_iron_grade_id')->nullable()->constrained('iron_grades')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('furnaces', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_iron_grade_id');
            $table->dropColumn('is_default');
        });
    }
};
