<?php

namespace Tests\Feature;

use App\Models\CastingAnalysis;
use App\Models\IronGrade;
use App\Models\Product;
use App\Services\Casting\MeshLoader;
use App\Services\Casting\SolidificationAnalyzer;
use App\Services\Casting\Voxelizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\StlFactory as F;
use Tests\TestCase;

class CastingAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private function analyse(string $stl, string $quality = 'DRAFT'): array
    {
        $f = tempnam(sys_get_temp_dir(), 't').'.stl';
        file_put_contents($f, $stl);
        $mesh = (new MeshLoader())->load($f, 'STL', 1.0);
        $vx = new Voxelizer();
        $plan = $vx->plan($mesh, $quality);
        $grid = $vx->voxelize($mesh, $plan['h']);
        $res = (new SolidificationAnalyzer())->analyze($mesh, $grid, ['wall_advised_mm' => 6]);
        @unlink($f);

        return [$res, $mesh];
    }

    protected function setUp(): void
    {
        parent::setUp();
        ini_set('memory_limit', '1G');
    }

    public function test_cube_modulus_matches_theory(): void
    {
        [$r, $mesh] = $this->analyse(F::cells(F::box(6, 6, 6), 10));   // 60 mm cube: M = a/6 = 1.0 cm
        $this->assertEqualsWithDelta(216.0, $mesh->volumeMm3 / 1000, 0.01);
        $this->assertEqualsWithDelta(1.0, $r['modulus']['max_cm'], 0.06);
        $this->assertCount(1, $r['hotspots']);
        $this->assertSame('PRIMARY', $r['hotspots'][0]['role']);
        // The hotspot sits at the middle of the cube.
        foreach ($r['hotspots'][0]['centre_mm'] as $c) {
            $this->assertEqualsWithDelta(30, $c, 3);
        }
    }

    public function test_sphere_modulus_is_one_third_radius(): void
    {
        [$r] = $this->analyse(F::sphere(30, 36));
        $this->assertEqualsWithDelta(1.0, $r['modulus']['max_cm'], 0.08);
    }

    public function test_plate_modulus_is_half_thickness_and_no_cut_off_hotspot(): void
    {
        [$r] = $this->analyse(F::cells(F::box(20, 10, 1), 10));   // 200 x 100 x 10 plate
        $this->assertEqualsWithDelta(0.5, $r['modulus']['p50_cm'], 0.05);
        $this->assertCount(1, $r['hotspots']);
        $this->assertSame('Z', $r['moulding']['axis']);          // laid flat, parted across its thickness
        $this->assertEqualsWithDelta(0.0, $r['moulding']['undercut_pct'], 0.1);
    }

    public function test_dumbbell_has_a_cut_off_hotspot(): void
    {
        $cells = array_merge(F::box(4, 4, 4), F::box(4, 1, 1, 1, [4, 1, 1]), F::box(4, 4, 4, 1, [8, 0, 0]));
        [$r] = $this->analyse(F::cells($cells, 10));   // two 40 mm blocks joined by a 10 mm neck
        $roles = array_column($r['hotspots'], 'role');
        $this->assertContains('PRIMARY', $roles);
        $this->assertContains('ISOLATED_SEVERE', $roles);
        $iso = collect($r['hotspots'])->firstWhere('role', 'ISOLATED_SEVERE');
        $this->assertLessThan(0.5, $iso['neck_ratio']);
    }

    public function test_t_junction_is_hotter_than_its_legs(): void
    {
        $cells = array_merge(F::box(10, 2, 2), F::box(2, 2, 8, 1, [4, 0, 2]));
        [$r] = $this->analyse(F::cells($cells, 5), 'STANDARD');
        $this->assertGreaterThan(1.2 * $r['modulus']['p50_cm'], $r['modulus']['max_cm']);
        $this->assertCount(1, $r['hotspots']);
    }

    public function test_closed_cavity_is_found(): void
    {
        $solid = F::box(6, 6, 6);
        $in = array_flip(array_map(fn ($c) => implode(',', $c), F::box(2, 2, 2, 1, [2, 2, 2])));
        $hollow = array_values(array_filter($solid, fn ($c) => ! isset($in[implode(',', $c)])));
        [$r] = $this->analyse(F::cells($hollow, 10));
        $this->assertCount(1, $r['voids']);
        $this->assertEqualsWithDelta(8.0, $r['voids'][0]['volume_cm3'], 1.0);   // 20 mm cube
    }

    public function test_undercut_is_flagged_for_an_overhang(): void
    {
        // An L-shape hooked over: a bar with a lip that overhangs in every axis direction we can split on.
        $cells = array_merge(F::box(1, 1, 5), F::box(3, 1, 1, 1, [0, 0, 4]), F::box(1, 1, 1, 1, [2, 0, 3]), F::box(1, 3, 1, 1, [0, 0, 0]));
        [$r] = $this->analyse(F::cells($cells, 10));
        $this->assertGreaterThanOrEqual(0, $r['moulding']['undercut_pct']);
        $this->assertContains($r['moulding']['axis'], ['X', 'Y', 'Z']);
    }

    public function test_bad_files_are_refused_with_a_clear_message(): void
    {
        $f = tempnam(sys_get_temp_dir(), 'b');
        file_put_contents($f, 'this is not an STL file at all');
        $this->expectException(\App\Services\Casting\CastingAnalysisException::class);
        (new MeshLoader())->load($f, 'STL', 1.0);
    }

    public function test_step_without_converter_gives_a_helpful_message(): void
    {
        config(['casting.step_converter' => '']);
        $f = tempnam(sys_get_temp_dir(), 's');
        file_put_contents($f, "ISO-10303-21;\nHEADER;\n");
        try {
            (new MeshLoader())->load($f, 'STEP', 1.0);
            $this->fail('expected exception');
        } catch (\App\Services\Casting\CastingAnalysisException $e) {
            $this->assertStringContainsString('Export the part as STL', $e->getMessage());
        }
    }

    public function test_upload_runs_the_analysis_and_shows_the_result(): void
    {
        Storage::fake('local');
        config(['foundry.attachments.disk' => 'local']);
        $product = Product::create(['code' => 'PRD-T1', 'name' => 'Test bracket', 'product_type' => 'FINISHED', 'base_uom' => 'EA', 'nominal_weight_kg' => 1.53, 'active' => true]);
        $this->assertTrue(IronGrade::where('code', 'EN-GJS-500-7')->exists(), 'the standard grades are seeded by migration');

        $stl = F::cells(F::box(6, 6, 6), 10);   // 216 cm3 -> 1.53 kg at 7.1
        $file = UploadedFile::fake()->createWithContent('cube.stl', $stl);
        $resp = $this->post(route('casting-analyses.store'), [
            'cad' => $file, 'units' => 'mm', 'quality' => 'DRAFT', 'family' => 'DUCTILE', 'feeder' => 'AUTO',
            'product_id' => $product->id, 'min_tensile' => 450, 'min_elongation' => 5,
        ]);
        $a = CastingAnalysis::firstOrFail();
        $resp->assertRedirect(route('casting-analyses.show', $a));
        $this->assertSame('COMPLETE', $a->status, (string) $a->error);
        $this->assertMatchesRegularExpression('/^CAN-\d{5}$/', $a->code);
        $this->assertNotNull($a->score);

        $this->get(route('casting-analyses.show', $a))->assertOk()->assertSee('Manufacturability')->assertSee('Chemistry')->assertSee('Freezing map');
        $this->get(route('casting-analyses.field', $a))->assertOk()->assertHeader('Content-Encoding', 'gzip');
        $this->get(route('casting-analyses.index'))->assertOk()->assertSee($a->code);
        $this->get(route('products.show', $product))->assertOk()->assertSee($a->code);

        $rec = $a->recommendation;
        $this->assertEqualsWithDelta(1.53, $rec['weight']['casting_kg'], 0.05);
        $this->assertNull($rec['weight']['note']);   // matches the product weight, so no unit warning
        $this->assertSame('DUCTILE', $rec['family']);
        $this->assertNotEmpty($rec['feeders']);
        $this->assertNotEmpty($rec['melt']['grade_candidates']);
        $this->assertSame('EN-GJS-500-7', $rec['melt']['grade_candidates'][0]['code']);
    }

    public function test_wrong_units_are_caught_by_the_product_weight(): void
    {
        Storage::fake('local');
        config(['foundry.attachments.disk' => 'local']);
        $product = Product::create(['code' => 'PRD-T2', 'name' => 'Cube', 'product_type' => 'FINISHED', 'base_uom' => 'EA', 'nominal_weight_kg' => 1.53, 'active' => true]);
        // 6 x 6 x 6 "cm" written as 6 units, then read as millimetres by mistake.
        $stl = F::cells(F::box(6, 6, 6), 1);
        $resp = $this->post(route('casting-analyses.store'), ['cad' => UploadedFile::fake()->createWithContent('small.stl', $stl), 'units' => 'mm', 'quality' => 'DRAFT', 'family' => 'GREY', 'feeder' => 'AUTO', 'product_id' => $product->id]);
        $this->assertNotNull($resp ?? null);
        $a = CastingAnalysis::firstOrFail();
        $this->assertNotNull($a->recommendation['weight']['note'], (string) $a->error);
        $this->assertStringContainsString('units', $a->recommendation['weight']['note']);
    }

    public function test_non_cad_upload_is_rejected(): void
    {
        Storage::fake('local');
        $this->post(route('casting-analyses.store'), ['cad' => UploadedFile::fake()->createWithContent('x.txt', 'hello'), 'units' => 'mm', 'quality' => 'DRAFT', 'family' => 'AUTO', 'feeder' => 'AUTO'])
            ->assertSessionHasErrors('cad');
        $this->assertSame(0, CastingAnalysis::count());
    }

    public function test_failed_analysis_is_saved_with_a_reason(): void
    {
        Storage::fake('local');
        config(['foundry.attachments.disk' => 'local']);
        $this->post(route('casting-analyses.store'), ['cad' => UploadedFile::fake()->createWithContent('bad.stl', 'solid x endsolid x'), 'units' => 'mm', 'quality' => 'DRAFT', 'family' => 'AUTO', 'feeder' => 'AUTO']);
        $a = CastingAnalysis::firstOrFail();
        $this->assertSame('FAILED', $a->status);
        $this->get(route('casting-analyses.show', $a))->assertOk()->assertSee('did not complete');
    }

    public function test_cut_off_hotspot_lowers_the_score_and_gets_a_chill(): void
    {
        Storage::fake('local');
        config(['foundry.attachments.disk' => 'local']);
        $cells = array_merge(F::box(4, 4, 4), F::box(4, 1, 1, 1, [4, 1, 1]), F::box(4, 4, 4, 1, [8, 0, 0]));
        $this->post(route('casting-analyses.store'), ['cad' => UploadedFile::fake()->createWithContent('db.stl', F::cells($cells, 10)), 'units' => 'mm', 'quality' => 'DRAFT', 'family' => 'DUCTILE', 'feeder' => 'AUTO']);
        $a = CastingAnalysis::firstOrFail();
        $this->assertSame('COMPLETE', $a->status, (string) $a->error);
        $this->assertNotEmpty($a->recommendation['chills']);
        $this->assertNotEmpty($a->recommendation['design_changes']);
        $this->assertLessThan(85, $a->score);
        $this->assertGreaterThanOrEqual(2, count($a->recommendation['feeders']));
    }
}
