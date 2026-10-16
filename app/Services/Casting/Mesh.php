<?php

namespace App\Services\Casting;

/** A triangle mesh in millimetres. $tri holds 9 floats per triangle (x0,y0,z0,x1,y1,z1,x2,y2,z2). */
class Mesh
{
    public array $tri = [];
    public int $count = 0;
    public array $min = [0, 0, 0];
    public array $max = [0, 0, 0];
    public float $volumeMm3 = 0.0;
    public float $areaMm2 = 0.0;
    public bool $normalsInverted = false;
    /** null = not checked, true/false = every edge shared by exactly two triangles */
    public ?bool $watertight = null;
    public int $openEdges = 0;
    public string $format = 'STL';

    public function size(): array
    {
        return [$this->max[0] - $this->min[0], $this->max[1] - $this->min[1], $this->max[2] - $this->min[2]];
    }

    public function finish(): void
    {
        $n = intdiv(count($this->tri), 9);
        $this->count = $n;
        if ($n === 0) {
            throw new CastingAnalysisException('The file contains no triangles.');
        }
        $t = $this->tri;
        $min = [INF, INF, INF];
        $max = [-INF, -INF, -INF];
        $vol = 0.0;
        $area = 0.0;
        for ($i = 0, $o = 0; $i < $n; $i++, $o += 9) {
            for ($k = 0; $k < 9; $k += 3) {
                $x = $t[$o + $k]; $y = $t[$o + $k + 1]; $z = $t[$o + $k + 2];
                if (! is_finite($x) || ! is_finite($y) || ! is_finite($z)) {
                    throw new CastingAnalysisException('The file contains invalid coordinates.');
                }
                if ($x < $min[0]) $min[0] = $x; if ($x > $max[0]) $max[0] = $x;
                if ($y < $min[1]) $min[1] = $y; if ($y > $max[1]) $max[1] = $y;
                if ($z < $min[2]) $min[2] = $z; if ($z > $max[2]) $max[2] = $z;
            }
            $ax = $t[$o]; $ay = $t[$o + 1]; $az = $t[$o + 2];
            $bx = $t[$o + 3]; $by = $t[$o + 4]; $bz = $t[$o + 5];
            $cx = $t[$o + 6]; $cy = $t[$o + 7]; $cz = $t[$o + 8];
            $vol += ($ax * ($by * $cz - $bz * $cy) - $ay * ($bx * $cz - $bz * $cx) + $az * ($bx * $cy - $by * $cx)) / 6.0;
            $ux = $bx - $ax; $uy = $by - $ay; $uz = $bz - $az;
            $vx = $cx - $ax; $vy = $cy - $ay; $vz = $cz - $az;
            $area += 0.5 * sqrt(($uy * $vz - $uz * $vy) ** 2 + ($uz * $vx - $ux * $vz) ** 2 + ($ux * $vy - $uy * $vx) ** 2);
        }
        $this->min = $min;
        $this->max = $max;
        if ($vol < 0) {
            $this->normalsInverted = true;
            $vol = -$vol;
        }
        $this->volumeMm3 = $vol;
        $this->areaMm2 = $area;
        if ($vol < 1e-6) {
            throw new CastingAnalysisException('The model has no enclosed volume. It must be a closed solid, not a surface or sheet.');
        }
    }

    /** Every edge of a closed solid is shared by exactly two triangles. Skipped for very large meshes. */
    public function checkClosed(int $limit = 200000): void
    {
        if ($this->count > $limit) {
            $this->watertight = null;

            return;
        }
        $size = max($this->size());
        $q = 1.0 / max($size * 1e-5, 1e-9);
        $ids = [];
        $edges = [];
        $t = $this->tri;
        for ($i = 0, $o = 0; $i < $this->count; $i++, $o += 9) {
            $v = [];
            for ($k = 0; $k < 9; $k += 3) {
                $key = (int) round($t[$o + $k] * $q).','.(int) round($t[$o + $k + 1] * $q).','.(int) round($t[$o + $k + 2] * $q);
                $v[] = $ids[$key] ??= count($ids);
            }
            foreach ([[0, 1], [1, 2], [2, 0]] as [$a, $b]) {
                if ($v[$a] === $v[$b]) {
                    continue;
                }
                $e = $v[$a] < $v[$b] ? $v[$a].'-'.$v[$b] : $v[$b].'-'.$v[$a];
                $edges[$e] = ($edges[$e] ?? 0) + 1;
            }
        }
        $open = 0;
        foreach ($edges as $c) {
            if ($c !== 2) {
                $open++;
            }
        }
        $this->openEdges = $open;
        $this->watertight = $open === 0;
    }
}
