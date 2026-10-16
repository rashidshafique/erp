<?php

namespace App\Services\Casting;

/** Fills a grid by casting a ray up every column and pairing the crossings (inside / outside / inside ...). */
class Voxelizer
{
    public const PAD = 4;

    /** Pick a cell size from the quality setting and the average wall, within the cell budget. */
    public function plan(Mesh $mesh, string $quality): array
    {
        $cells = (int) (config('casting.resolution')[$quality] ?? config('casting.resolution.STANDARD'));
        $max = (int) config('casting.max_cells');
        [$sx, $sy, $sz] = $mesh->size();
        $box = max($sx, 1e-6) * max($sy, 1e-6) * max($sz, 1e-6);
        $h = pow($box / $cells, 1 / 3);
        $avgWall = 2 * $mesh->volumeMm3 / max($mesh->areaMm2, 1e-9);
        $want = $avgWall * (float) config('casting.voxel_to_wall');
        $refined = false;
        if ($h > $want) {
            $h2 = max($want, pow($box / $max, 1 / 3));
            $refined = $h2 < $h;
            $h = $h2;
        }
        // Flat parts: the box is thin in one direction, so make sure there are still a few cells across it.
        $h = min($h, max(min($sx, $sy, $sz), 1e-6) / 8);
        $h = max($h, pow(max($box, 1e-9) / $max, 1 / 3) * 0.5);
        while ((($sx / $h) + 2 * self::PAD) * (($sy / $h) + 2 * self::PAD) * (($sz / $h) + 2 * self::PAD) > $max * 1.05) {
            $h *= 1.08;
        }

        return ['h' => $h, 'avg_wall' => $avgWall, 'coarse' => $h > $avgWall * 0.5, 'refined' => $refined];
    }

    public function voxelize(Mesh $mesh, float $h): Grid
    {
        $g = new Grid();
        $g->h = $h;
        [$sx, $sy, $sz] = $mesh->size();
        $pad = self::PAD;
        $g->nx = (int) ceil($sx / $h) + 2 * $pad;
        $g->ny = (int) ceil($sy / $h) + 2 * $pad;
        $g->nz = (int) ceil($sz / $h) + 2 * $pad;
        $g->origin = [$mesh->min[0] - $pad * $h, $mesh->min[1] - $pad * $h, $mesh->min[2] - $pad * $h];
        $nx = $g->nx; $ny = $g->ny; $nz = $g->nz;
        $ox = $g->origin[0]; $oy = $g->origin[1]; $oz = $g->origin[2];
        // Tiny offsets keep rays off edges and vertices that sit exactly on a cell centre.
        $jx = $h * 1.7e-4 * 1.41421356;
        $jy = $h * 2.3e-4 * 1.73205081;
        $cols = [];
        $t = $mesh->tri;

        for ($f = 0, $o = 0; $f < $mesh->count; $f++, $o += 9) {
            $x0 = $t[$o]; $y0 = $t[$o + 1]; $z0 = $t[$o + 2];
            $x1 = $t[$o + 3]; $y1 = $t[$o + 4]; $z1 = $t[$o + 5];
            $x2 = $t[$o + 6]; $y2 = $t[$o + 7]; $z2 = $t[$o + 8];
            $det = ($y1 - $y2) * ($x0 - $x2) + ($x2 - $x1) * ($y0 - $y2);
            if (abs($det) < 1e-14) {
                continue;
            }
            $i0 = max(0, (int) ceil((min($x0, $x1, $x2) - $ox - $jx) / $h - 0.5));
            $i1 = min($nx - 1, (int) floor((max($x0, $x1, $x2) - $ox - $jx) / $h - 0.5));
            $j0 = max(0, (int) ceil((min($y0, $y1, $y2) - $oy - $jy) / $h - 0.5));
            $j1 = min($ny - 1, (int) floor((max($y0, $y1, $y2) - $oy - $jy) / $h - 0.5));
            if ($i1 < $i0 || $j1 < $j0) {
                continue;
            }
            $inv = 1.0 / $det;
            $a1 = ($y1 - $y2) * $inv; $b1 = ($x2 - $x1) * $inv;
            $a2 = ($y2 - $y0) * $inv; $b2 = ($x0 - $x2) * $inv;
            for ($j = $j0; $j <= $j1; $j++) {
                $py = $oy + ($j + 0.5) * $h + $jy;
                $dy = $py - $y2;
                for ($i = $i0; $i <= $i1; $i++) {
                    $px = $ox + ($i + 0.5) * $h + $jx;
                    $dx = $px - $x2;
                    $l0 = $a1 * $dx + $b1 * $dy;
                    if ($l0 < 0) continue;
                    $l1 = $a2 * $dx + $b2 * $dy;
                    if ($l1 < 0) continue;
                    $l2 = 1.0 - $l0 - $l1;
                    if ($l2 < 0) continue;
                    $cols[$j * $nx + $i][] = $l0 * $z0 + $l1 * $z1 + $l2 * $z2;
                }
            }
        }

        $solid = str_repeat("\0", $nx * $ny * $nz);
        $plane = $nx * $ny;
        $count = 0;
        $odd = 0;
        foreach ($cols as $c => $zs) {
            sort($zs, SORT_NUMERIC);
            $n = count($zs);
            if ($n % 2 === 1) {
                $odd++;
                array_pop($zs);
                $n--;
            }
            for ($k = 0; $k < $n; $k += 2) {
                $ka = max(0, (int) ceil(($zs[$k] - $oz) / $h - 0.5));
                $kb = min($nz - 1, (int) floor(($zs[$k + 1] - $oz) / $h - 0.5));
                for ($z = $ka; $z <= $kb; $z++) {
                    $solid[$z * $plane + $c] = "\1";
                    $count++;
                }
            }
        }
        $g->solid = $solid;
        $g->solidCount = $count;
        $g->hitColumns = count($cols);
        $g->oddColumns = $odd;
        if ($count === 0) {
            throw new CastingAnalysisException('The model is too thin for the chosen resolution (no cell fell inside it). Use Fine quality or check the units.');
        }

        return $g;
    }
}
