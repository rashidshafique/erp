<?php

namespace App\Services\Casting;

use App\Models\CastingAnalysis;
use App\Models\IronGrade;
use Illuminate\Support\Facades\Storage;

/** Runs the whole study for one stored CAD file and saves the outcome on the record. */
class CastingAnalysisService
{
    public function __construct(
        private MeshLoader $loader = new MeshLoader(),
        private Voxelizer $voxelizer = new Voxelizer(),
        private SolidificationAnalyzer $analyzer = new SolidificationAnalyzer(),
        private Recommender $recommender = new Recommender(),
    ) {
    }

    public function run(CastingAnalysis $a): CastingAnalysis
    {
        @ini_set('memory_limit', (string) config('casting.memory_limit'));
        @set_time_limit((int) config('casting.time_limit_seconds'));
        $t0 = microtime(true);
        $tmp = null;
        try {
            $disk = Storage::disk($a->file_disk);
            if (! $disk->exists($a->file_path)) {
                throw new CastingAnalysisException('The CAD file is missing from storage.');
            }
            // Works on any disk: copy to a temporary local file.
            $tmp = tempnam(sys_get_temp_dir(), 'cad');
            file_put_contents($tmp, $disk->get($a->file_path));
            $toMm = (float) (config('casting.units_to_mm')[$a->units] ?? 1.0);

            $mesh = $this->loader->load($tmp, $a->source_type, $toMm);
            $plan = $this->voxelizer->plan($mesh, $a->quality);
            $grid = $this->voxelizer->voxelize($mesh, $plan['h']);

            $opt = $a->options ?? [];
            $grade = $a->grade;
            $gradeArr = $grade ? $grade->only(['code', 'family', 'min_tensile_mpa', 'min_yield_mpa', 'min_elongation_pct', 'c_min', 'c_max', 'si_min', 'si_max', 'mn_min', 'mn_max', 'p_min', 'p_max', 's_min', 's_max', 'cr_min', 'cr_max', 'cu_min', 'cu_max', 'mg_min', 'mg_max', 'pour_temp_min_c', 'pour_temp_max_c']) : null;
            $famGuess = $gradeArr['family'] ?? (in_array($a->family_requested, ['GREY', 'DUCTILE'], true) ? $a->family_requested : 'DUCTILE');
            $wallAdv = (float) config("casting.families.$famGuess.wall_advised_min_mm", 6);

            $res = $this->analyzer->analyze($mesh, $grid, ['wall_advised_mm' => $wallAdv]);
            $quality = $this->quality($mesh, $grid, $plan, $res);
            $rec = $this->recommender->build($res, [
                'size_mm' => array_map(fn ($v) => round($v, 2), $mesh->size()),
                'volume_cm3' => $mesh->volumeMm3 / 1000,
                'nominal_weight_kg' => $a->product?->nominal_weight_kg !== null ? (float) $a->product->nominal_weight_kg : null,
            ], [
                'family' => $a->family_requested, 'grade' => $gradeArr,
                'min_tensile' => $opt['min_tensile'] ?? null, 'min_elongation' => $opt['min_elongation'] ?? null,
                'pour_window_min' => $opt['pour_window_min'] ?? 10, 'feeder' => $opt['feeder'] ?? 'AUTO', 'b_override' => $opt['b_override'] ?? null,
                'grades' => IronGrade::where('status', 'ACTIVE')->get()->map->only(['code', 'name', 'family', 'min_tensile_mpa', 'min_yield_mpa', 'min_elongation_pct'])->all(),
            ]);

            $fieldPath = 'casting-analyses/'.$a->id.'.field.gz';
            $disk->put($fieldPath, gzencode($res['field'], 6));
            $cloud = $res['cloud'];
            unset($res['field'], $res['cloud']);
            $res['quality'] = $quality;
            $res['size_mm'] = array_map(fn ($v) => round($v, 2), $mesh->size());
            $res['bbox_min'] = array_map(fn ($v) => round($v, 3), $mesh->min);
            $res['triangles'] = $mesh->count;

            $a->fill([
                'status' => 'COMPLETE', 'error' => null, 'family' => $rec['family'], 'score' => $rec['score']['total'], 'score_letter' => $rec['score']['letter'],
                'result' => $res, 'recommendation' => $rec, 'field_path' => $fieldPath,
                'viewer' => ['dims' => [$grid->nx, $grid->ny, $grid->nz], 'h' => $grid->h, 'origin' => $grid->origin, 'm_max_cm' => $res['modulus']['max_cm'], 'b' => $rec['b'],
                    'ghost' => $cloud['ghost'], 'hot' => $cloud['hot'],
                    'hotspots' => array_map(fn ($h) => ['id' => $h['id'], 'role' => $h['role'], 'c' => $h['centre_mm'], 'm' => $h['modulus_cm']], $res['hotspots'])],
                'duration_ms' => (int) ((microtime(true) - $t0) * 1000),
            ])->save();
        } catch (CastingAnalysisException $e) {
            $a->fill(['status' => 'FAILED', 'error' => $e->getMessage(), 'duration_ms' => (int) ((microtime(true) - $t0) * 1000)])->save();
        } catch (\Throwable $e) {
            report($e);
            $a->fill(['status' => 'FAILED', 'error' => 'The analysis stopped unexpectedly ('.class_basename($e).'). The details are in the application log.', 'duration_ms' => (int) ((microtime(true) - $t0) * 1000)])->save();
        } finally {
            if ($tmp && is_file($tmp)) {
                @unlink($tmp);
            }
        }

        return $a;
    }

    /** How far to trust the numbers, with the reasons. */
    private function quality(Mesh $mesh, Grid $grid, array $plan, array $res): array
    {
        $warn = [];
        $level = 0;   // 0 high, 1 medium, 2 low
        if ($mesh->watertight === false) {
            $warn[] = "The mesh is not closed ({$mesh->openEdges} open edges). Gaps can make volumes and walls wrong; repair the model in your CAD or a mesh tool.";
            $level = max($level, 1);
        } elseif ($mesh->watertight === null) {
            $warn[] = 'The mesh is large, so the closed-surface check was skipped.';
        }
        if ($mesh->normalsInverted) {
            $warn[] = 'The model\'s face directions were inverted; this was corrected automatically.';
        }
        if ($grid->hitColumns > 0 && $grid->oddColumns / $grid->hitColumns > 0.02) {
            $warn[] = sprintf('%.1f%% of the sampling columns crossed the surface an odd number of times (holes or overlapping shells in the mesh).', $grid->oddColumns / $grid->hitColumns * 100);
            $level = max($level, 2);
        }
        if ($plan['coarse']) {
            $warn[] = sprintf('Cell size %.1f mm is coarse for a wall averaging %.1f mm. Use Fine quality.', $grid->h, $plan['avg_wall']);
            $level = max($level, 1);
        }
        $err = abs($res['volume']['error_pct']);
        if ($err > 5) {
            $warn[] = sprintf('Voxel volume differs from the mesh volume by %.1f%%.', $res['volume']['error_pct']);
            $level = max($level, $err > 12 ? 2 : 1);
        }
        if ($res['lost_area_pct'] > 2) {
            $warn[] = sprintf('%.1f%% of the surface could not be placed in the grid.', $res['lost_area_pct']);
            $level = max($level, 1);
        }
        if ($res['thickness']['unresolved_pct'] > 10) {
            $warn[] = sprintf('%.0f%% of the surface has walls thinner than one cell, so thickness is unreliable there.', $res['thickness']['unresolved_pct']);
            $level = max($level, 1);
        }

        return ['confidence' => ['HIGH', 'MEDIUM', 'LOW'][$level], 'warnings' => $warn, 'cell_mm' => $grid->h, 'refined' => $plan['refined'], 'volume_error_pct' => $res['volume']['error_pct']];
    }
}
