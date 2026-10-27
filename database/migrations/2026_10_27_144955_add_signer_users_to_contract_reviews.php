<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_reviews', function (Blueprint $table) {
            $table->foreignId('sales_signed_by_user_id')->nullable()->after('sales_signed_by')->constrained('users')->nullOnDelete();
            $table->foreignId('quality_signed_by_user_id')->nullable()->after('quality_signed_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contract_reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_signed_by_user_id');
            $table->dropConstrainedForeignId('quality_signed_by_user_id');
        });
    }
};
