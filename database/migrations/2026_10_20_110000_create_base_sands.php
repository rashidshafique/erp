<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Base sands: the refractory grain a sand system is made of (silica, chromite, zircon…), each one optionally backed by a raw
 * material in inventory. A sand system uses one or more of them, one being its main sand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('base_sands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->foreignUuid('material_id')->nullable()->unique()->constrained('materials')->nullOnDelete();
            $table->string('status', 20)->default('ACTIVE');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sand_system_sands', function (Blueprint $table) {
            $table->foreignUuid('sand_system_id')->constrained('sand_systems')->cascadeOnDelete();
            $table->foreignUuid('base_sand_id')->constrained('base_sands')->restrictOnDelete();
            $table->boolean('is_main')->default(false);
            $table->timestamps();

            $table->primary(['sand_system_id', 'base_sand_id']);
        });
        DB::statement('CREATE UNIQUE INDEX sand_system_sands_one_main ON sand_system_sands (sand_system_id) WHERE is_main');

        $sands = [
            ['SILICA', 'Silica sand', 'Quartz sand, the general-purpose moulding grain. High thermal expansion, so large flat surfaces can vein.'],
            ['CHROMITE', 'Chromite sand', 'Chills well and resists metal penetration; used for steel, heavy sections and facing hot spots.'],
            ['ZIRCON', 'Zircon sand', 'Highest refractoriness and lowest expansion; for fine finish and difficult steel castings. Costly, often used as a facing.'],
            ['OLIVINE', 'Olivine sand', 'Basic (not acidic) grain; the usual choice for manganese steel.'],
            ['CERAMIC', 'Ceramic sand (synthetic)', 'Manufactured round grains with low expansion and good reclamation; used in cores and no-bake moulds.'],
            ['CHAMOTTE', 'Chamotte (fireclay grog)', 'Coarse calcined fireclay; a refractory filler for loam and very large moulds.'],
        ];
        $ids = [];
        foreach ($sands as $i => [$code, $name, $description]) {
            $ids[$code] = (string) Str::uuid();
            DB::table('base_sands')->insert([
                'id' => $ids[$code], 'code' => $code, 'name' => $name, 'description' => $description, 'status' => 'ACTIVE',
                'sort_order' => ($i + 1) * 10, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // The sand systems seeded with the application are all silica based.
        $silicaSystems = DB::table('sand_systems')->whereNull('deleted_at')
            ->whereIn(DB::raw('lower(name)'), ['greensand', 'sheera', 'alphaset', 'resin-coated sand'])->pluck('id');
        foreach ($silicaSystems as $systemId) {
            DB::table('sand_system_sands')->insert([
                'sand_system_id' => $systemId, 'base_sand_id' => $ids['SILICA'], 'is_main' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sand_system_sands');
        Schema::dropIfExists('base_sands');
    }
};
