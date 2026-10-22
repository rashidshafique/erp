<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A recorder (DVR/NVR) the app can reach, and the cameras (its channels) shown on the camera page. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dvrs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('brand', 20)->default('DAHUA');
            $table->string('protocol', 5)->default('http');
            $table->string('host', 120);
            $table->unsignedInteger('port')->default(80);
            $table->string('username', 100);
            $table->text('password');
            $table->boolean('active')->default(true);
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('cameras', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dvr_id')->constrained('dvrs')->cascadeOnDelete();
            $table->unsignedSmallInteger('channel');
            $table->string('name', 100);
            $table->string('area', 100)->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['dvr_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cameras');
        Schema::dropIfExists('dvrs');
    }
};
