<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('casting_lots', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->unique();
        });

        $serials = [];
        DB::table('casting_lots')->orderBy('created_at')->orderBy('id')->get(['id', 'created_from_pour_at', 'created_at'])->each(function ($lot) use (&$serials) {
            $day = date('ymd', strtotime((string) ($lot->created_from_pour_at ?? $lot->created_at)));
            $serials[$day] = ($serials[$day] ?? 0) + 1;
            DB::table('casting_lots')->where('id', $lot->id)->update(['code' => $day.'-'.str_pad((string) $serials[$day], 2, '0', STR_PAD_LEFT)]);
        });
    }

    public function down(): void
    {
        Schema::table('casting_lots', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
