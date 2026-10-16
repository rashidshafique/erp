<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $t) {
            $t->boolean('is_cover')->default(false);   // the main photo shown in the record's summary
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $t) {
            $t->dropColumn('is_cover');
        });
    }
};
