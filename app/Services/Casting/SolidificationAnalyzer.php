<?php

namespace App\Services\Casting;

/**
 * Geometric solidification study. In a sand mould the mould, not the metal, limits heat loss, so freezing time
 * follows Chvorinov: t = B * M^2, where M = volume / cooling area. We measure M locally everywhere in the part
 * (volume and mould-contact area inside a window sized to the local wall), then follow how M rises and falls to
 * find the last places to freeze and whether each one can still be fed through a path that stays liquid.
 */
class SolidificationAnalyzer
{
    private array $S = [];       // surface samples: px,py,pz,nx,ny,nz,a,idx

    public function analyze(Mesh $mesh, Grid $g, array $opts = []): array
    {
        $cfg = config('casting.modulus');
        $h = $g->h;
        $nx = $g->nx; $ny = $g->ny; $nz = $g->nz;
        $N = $g->cells();
        $plane = $nx * $ny;

        $area = $this->sampleSurface($mesh, $g);
        $lostArea = $this->S['lost'];
        $r2 = $this->distanceSquared($g);
        [$PV, $PA] = $this->prefixSums($g, $area);
        unset($area);

        // ---- section modulus (mm) ----------------------------------------------------------------------
        // Every cell climbs the distance field to the ridge (centre line / centre plane) of its own section. The ridge
        // cell sees all the cooling faces around it, so its volume / area is the modulus of the whole section. Cells
        // share the value of the ridge they climb to.
        $M = array_fill(0, $N, 0.0);
        $sy = $nx + 1;
        $sz = ($nx + 1) * ($ny + 1);
        $kWin = (float) $cfg['window_radii'];
        $h3 = $h * $h * $h;
        $solid = $g->solid;
        $solidIdx = [];
        $byR = [];
        for ($i = strpos($solid, "\1"); $i !== false; $i = strpos($solid, "\1", $i + 1)) {
            $solidIdx[] = $i;
            $byR[(int) round($r2[$i])][] = $i;
        }
        krsort($byR);
        $offs = [];
        for ($dz = -1; $dz <= 1; $dz++) {
            for ($dy = -1; $dy <= 1; $dy++) {
                for ($dx = -1; $dx <= 1; $dx++) {
                    if ($dx || $dy || $dz) {
                        $offs[] = $dz * $plane + $dy * $nx + $dx;
                    }
                }
            }
        }
        $anc = [];
        foreach ($byR as $rr => $cells) {
            foreach ($cells as $i) {
                $best = -1;
                $bestR = $rr;
                foreach ($offs as $o) {
                    $j = $i + $o;
                    if ($r2[$j] > $bestR) {
                        $bestR = $r2[$j];
                        $best = $j;
                    }
                }
                $anc[$i] = $best < 0 ? $i : $anc[$best];
            }
        }
        unset($byR);
        $ridgeM = [];
        $Mmax = 0.0;
        foreach ($anc as $i => $a0) {
            if ($a0 !== $i) {
                continue;
            }
            $x = $i % $nx; $y = intdiv($i, $nx) % $ny; $z = intdiv($i, $plane);
            $r = sqrt($r2[$i]) - 0.5;
            $w = max(2, (int) ceil($kWin * max($r, 0.5)));
            $x0 = max(0, $x - $w); $x1 = min($nx - 1, $x + $w);
            $y0 = max(0, $y - $w); $y1 = min($ny - 1, $y + $w);
            $z0 = max(0, $z - $w); $z1 = min($nz - 1, $z + $w);
            $b = $z1 + 1; $a = $z0;
            $X1 = $x1 + 1; $X0 = $x0; $Y1 = $y1 + 1; $Y0 = $y0;
            $p11 = $b * $sz + $Y1 * $sy; $p01 = $a * $sz + $Y1 * $sy; $p10 = $b * $sz + $Y0 * $sy; $p00 = $a * $sz + $Y0 * $sy;
            $v = $PV[$p11 + $X1] - $PV[$p01 + $X1] - $PV[$p10 + $X1] - $PV[$p11 + $X0] + $PV[$p00 + $X1] + $PV[$p01 + $X0] + $PV[$p10 + $X0] - $PV[$p00 + $X0];
            $ar = $PA[$p11 + $X1] - $PA[$p01 + $X1] - $PA[$p10 + $X1] - $PA[$p11 + $X0] + $PA[$p00 + $X1] + $PA[$p01 + $X0] + $PA[$p10 + $X0] - $PA[$p00 + $X0];
            $m = $ar > 1e-9 ? $v * $h3 / $ar : 0.0;
            $ridgeM[$i] = $m;
            if ($m > $Mmax) {
                $Mmax = $m;
            }
        }
        foreach ($anc as $i => $a0) {
            $M[$i] = $ridgeM[$a0];
        }
        unset($anc, $ridgeM);
        unset($PV, $PA);
        if ($Mmax <= 0) {
            throw new CastingAnalysisException('Could not measure any section modulus. Check the units and that the model is a closed solid.');
        }

        $hot = $this->findHotspots($g, $M, $Mmax, $solidIdx, $r2);
        $thickness = $this->wallThickness($g, $mesh);
        $thickness['thin_zones'] = $this->thinZones($thickness, (float) ($opts['wall_advised_mm'] ?? 4.0), max(10 * $h, 15.0));
        $thickness['hist'] = $this->histogram($thickness);
        unset($thickness['pairs']);
        $dfm = $this->mouldingStudy($mesh, $g, $hot['primary_centre'] ?? null);
        $voids = $this->closedVoids($g);

        // ---- summary numbers --------------------------------------------------------------------------
        $vVox = $g->solidCount * $h3;
        $sorted = [];
        foreach ($solidIdx as $i) {
            if ($M[$i] > 0) {
                $sorted[] = $M[$i];
            }
        }
        sort($sorted, SORT_NUMERIC);
        $n = count($sorted);
        $p50 = $sorted[(int) ($n * 0.5)] ?? 0;
        $p90 = $sorted[(int) ($n * 0.9)] ?? 0;
        $mPart = $mesh->volumeMm3 / max($mesh->areaMm2, 1e-9);

        // Field for the viewer: 0 = empty, 1..255 = modulus relative to the largest.
        $field = str_repeat("\0", $N);
        foreach ($solidIdx as $i) {
            $field[$i] = chr(max(1, min(255, (int) round($M[$i] / $Mmax * 255))));
        }

        return [
            'grid' => ['nx' => $nx, 'ny' => $ny, 'nz' => $nz, 'h_mm' => $h, 'origin' => $g->origin, 'cells' => $N, 'solid_cells' => $g->solidCount],
            'volume' => [
                'mesh_cm3' => $mesh->volumeMm3 / 1000, 'voxel_cm3' => $vVox / 1000,
                'error_pct' => $mesh->volumeMm3 > 0 ? ($vVox - $mesh->volumeMm3) / $mesh->volumeMm3 * 100 : 0,
            ],
            'area_cm2' => $mesh->areaMm2 / 100,
            'lost_area_pct' => $mesh->areaMm2 > 0 ? $lostArea / $mesh->areaMm2 * 100 : 0,
            'modulus' => [
                'part_cm' => $mPart / 10, 'max_cm' => $Mmax / 10, 'p50_cm' => $p50 / 10, 'p90_cm' => $p90 / 10,
            ],
            'hotspots' => $hot['list'],
            'noise_peaks' => $hot['noise'],
            'thickness' => $thickness,
            'moulding' => $dfm,
            'voids' => $voids,
            'field' => $field,
            'cloud' => $hot['cloud'] + ['ghost' => $this->ghostCloud($g)],
        ];
    }

    // ============================================================ surface samples
    private function sampleSurface(Mesh $mesh, Grid $g): array
    {
        $h = $g->h;
        $N = $g->cells();
        $area = array_fill(0, $N, 0.0);
        $spacing = max($h, sqrt($mesh->areaMm2 / 150000));
        $t = $mesh->tri;
        $flip = $mesh->normalsInverted ? -1.0 : 1.0;
        $S = ['px' => [], 'py' => [], 'pz' => [], 'nx' => [], 'ny' => [], 'nz' => [], 'a' => [], 'idx' => [], 'lost' => 0.0];
        $nxg = $g->nx; $plane = $g->nx * $g->ny;
        $solid = $g->solid;
        $ox = $g->origin[0]; $oy = $g->origin[1]; $oz = $g->origin[2];

        for ($f = 0, $o = 0; $f < $mesh->count; $f++, $o += 9) {
            $ax = $t[$o]; $ay = $t[$o + 1]; $az = $t[$o + 2];
            $ux = $t[$o + 3] - $ax; $uy = $t[$o + 4] - $ay; $uz = $t[$o + 5] - $az;
            $vx = $t[$o + 6] - $ax; $vy = $t[$o + 7] - $ay; $vz = $t[$o + 8] - $az;
            $cx = $uy * $vz - $uz * $vy; $cy = $uz * $vx - $ux * $vz; $cz = $ux * $vy - $uy * $vx;
            $len = sqrt($cx * $cx + $cy * $cy + $cz * $cz);
            if ($len < 1e-12) {
                continue;
            }
            $a = 0.5 * $len;
            $nxn = $cx / $len * $flip; $nyn = $cy / $len * $flip; $nzn = $cz / $len * $flip;
            // Confirm the outward side by probing the solid grid either side of the face.
            $mx = $ax + ($ux + $vx) / 3; $my = $ay + ($uy + $vy) / 3; $mz = $az + ($uz + $vz) / 3;
            $pr = 0.75 * $h;
            $plus = $g->isSolidAt($mx + $nxn * $pr, $my + $nyn * $pr, $mz + $nzn * $pr);
            $minus = $g->isSolidAt($mx - $nxn * $pr, $my - $nyn * $pr, $mz - $nzn * $pr);
            if ($plus && ! $minus) {
                $nxn = -$nxn; $nyn = -$nyn; $nzn = -$nzn;
            }
            $k = min(60, max(1, (int) ceil(sqrt($a) / $spacing)));
            $sa = $a / ($k * $k);
            for ($i = 0; $i < $k; $i++) {
                for ($j = 0; $j < $k - $i; $j++) {
                    for ($up = 0; $up < 2; $up++) {
                        if ($up === 1 && $j > $k - 2 - $i) {
                            break;
                        }
                        $l1 = $up ? ($i + 2 / 3) / $k : ($i + 1 / 3) / $k;
                        $l2 = $up ? ($j + 2 / 3) / $k : ($j + 1 / 3) / $k;
                        $px = $ax + $l1 * $ux + $l2 * $vx;
                        $py = $ay + $l1 * $uy + $l2 * $vy;
                        $pz = $az + $l1 * $uz + $l2 * $vz;
                        $idx = $this->nearestSolid($g, $px, $py, $pz, $nxn, $nyn, $nzn);
                        if ($idx < 0) {
                            $S['lost'] += $sa;
                            continue;
                        }
                        $area[$idx] += $sa;
                        $S['px'][] = $px; $S['py'][] = $py; $S['pz'][] = $pz;
                        $S['nx'][] = $nxn; $S['ny'][] = $nyn; $S['nz'][] = $nzn;
                        $S['a'][] = $sa; $S['idx'][] = $idx;
                    }
                }
            }
        }
        $this->S = $S;

        return $area;
    }

    /** The solid cell that takes a surface sample: the one holding it, else the closest neighbour, else one step inward. */
    private function nearestSolid(Grid $g, float $px, float $py, float $pz, float $nx, float $ny, float $nz): int
    {
        $h = $g->h;
        foreach ([0.0, 1.0] as $step) {
            $qx = $px - $nx * $h * $step; $qy = $py - $ny * $h * $step; $qz = $pz - $nz * $h * $step;
            $x = (int) floor(($qx - $g->origin[0]) / $h);
            $y = (int) floor(($qy - $g->origin[1]) / $h);
            $z = (int) floor(($qz - $g->origin[2]) / $h);
            $best = -1;
            $bd = INF;
            for ($dz = -1; $dz <= 1; $dz++) {
                for ($dy = -1; $dy <= 1; $dy++) {
                    for ($dx = -1; $dx <= 1; $dx++) {
                        $xx = $x + $dx; $yy = $y + $dy; $zz = $z + $dz;
                        if ($xx < 0 || $yy < 0 || $zz < 0 || $xx >= $g->nx || $yy >= $g->ny || $zz >= $g->nz) {
                            continue;
                        }
                        $i = ($zz * $g->ny + $yy) * $g->nx + $xx;
                        if ($g->solid[$i] !== "\1") {
                            continue;
                        }
                        $cx = $g->origin[0] + ($xx + 0.5) * $h - $qx;
                        $cy = $g->origin[1] + ($yy + 0.5) * $h - $qy;
                        $cz = $g->origin[2] + ($zz + 0.5) * $h - $qz;
                        $d = $cx * $cx + $cy * $cy + $cz * $cz;
                        if ($d < $bd) {
                            $bd = $d;
                            $best = $i;
                        }
                    }
                }
            }
            if ($best >= 0) {
                return $best;
            }
        }

        return -1;
    }

    // ============================================================ distance transform
    /** Squared distance (in cells) from each solid cell centre to the nearest empty cell centre. */
    private function distanceSquared(Grid $g): array
    {
        $nx = $g->nx; $ny = $g->ny; $nz = $g->nz; $N = $g->cells();
        $BIG = 1e12;
        $f = array_fill(0, $N, 0.0);
        $solid = $g->solid;
        for ($i = strpos($solid, "\1"); $i !== false; $i = strpos($solid, "\1", $i + 1)) {
            $f[$i] = $BIG;
        }
        $plane = $nx * $ny;
        // x
        for ($z = 0; $z < $nz; $z++) {
            for ($y = 0; $y < $ny; $y++) {
                $base = $z * $plane + $y * $nx;
                $p = strpos($solid, "\1", $base);
                if ($p === false || $p >= $base + $nx) {
                    continue;
                }
                $line = array_slice($f, $base, $nx);
                $d = $this->dt1($line, $nx);
                foreach ($d as $k => $val) {
                    $f[$base + $k] = $val;
                }
            }
        }
        // y
        for ($z = 0; $z < $nz; $z++) {
            for ($x = 0; $x < $nx; $x++) {
                $line = [];
                $base = $z * $plane + $x;
                for ($y = 0; $y < $ny; $y++) {
                    $line[] = $f[$base + $y * $nx];
                }
                $d = $this->dt1($line, $ny);
                foreach ($d as $y => $val) {
                    $f[$base + $y * $nx] = $val;
                }
            }
        }
        // z
        for ($y = 0; $y < $ny; $y++) {
            for ($x = 0; $x < $nx; $x++) {
                $line = [];
                $base = $y * $nx + $x;
                for ($z = 0; $z < $nz; $z++) {
                    $line[] = $f[$base + $z * $plane];
                }
                $d = $this->dt1($line, $nz);
                foreach ($d as $z => $val) {
                    $f[$base + $z * $plane] = $val;
                }
            }
        }

        return $f;
    }

    /** Exact 1-D squared distance transform (lower envelope of parabolas). */
    private function dt1(array $f, int $n): array
    {
        $d = array_fill(0, $n, 0.0);
        $v = [0];
        $z = [-INF, INF];
        $k = 0;
        for ($q = 1; $q < $n; $q++) {
            while (true) {
                $p = $v[$k];
                $s = (($f[$q] + $q * $q) - ($f[$p] + $p * $p)) / (2 * $q - 2 * $p);
                if ($s <= $z[$k]) {
                    $k--;
                    if ($k < 0) {
                        $k = 0;
                        break;
                    }
                } else {
                    break;
                }
            }
            $p = $v[$k];
            $s = (($f[$q] + $q * $q) - ($f[$p] + $p * $p)) / (2 * $q - 2 * $p);
            $k++;
            $v[$k] = $q;
            $z[$k] = $s;
            $z[$k + 1] = INF;
        }
        $k = 0;
        for ($q = 0; $q < $n; $q++) {
            while ($z[$k + 1] < $q) {
                $k++;
            }
            $dq = $q - $v[$k];
            $d[$q] = $dq * $dq + $f[$v[$k]];
        }

        return $d;
    }

    // ============================================================ prefix sums
    private function prefixSums(Grid $g, array $area): array
    {
        $nx = $g->nx; $ny = $g->ny; $nz = $g->nz;
        $sy = $nx + 1;
        $sz = ($nx + 1) * ($ny + 1);
        $T = $sz * ($nz + 1);
        $PV = array_fill(0, $T, 0);
        $PA = array_fill(0, $T, 0.0);
        $solid = $g->solid;
        $plane = $nx * $ny;
        for ($z = 0; $z < $nz; $z++) {
            for ($y = 0; $y < $ny; $y++) {
                $i = ($z + 1) * $sz + ($y + 1) * $sy + 1;
                $c = $z * $plane + $y * $nx;
                for ($x = 0; $x < $nx; $x++, $i++, $c++) {
                    $PV[$i] = ($solid[$c] === "\1" ? 1 : 0) + $PV[$i - 1] + $PV[$i - $sy] + $PV[$i - $sz] - $PV[$i - 1 - $sy] - $PV[$i - 1 - $sz] - $PV[$i - $sy - $sz] + $PV[$i - 1 - $sy - $sz];
                    $PA[$i] = $area[$c] + $PA[$i - 1] + $PA[$i - $sy] + $PA[$i - $sz] - $PA[$i - 1 - $sy] - $PA[$i - 1 - $sz] - $PA[$i - $sy - $sz] + $PA[$i - 1 - $sy - $sz];
                }
            }
        }

        return [$PV, $PA];
    }

    // ============================================================ hotspots
    private function findHotspots(Grid $g, array $M, float $Mmax, array $solidIdx, array $r2): array
    {
        $cfg = config('casting.modulus');
        $h = $g->h;
        $nx = $g->nx; $plane = $g->nx * $g->ny;
        $offs = [];
        for ($dz = -1; $dz <= 1; $dz++) {
            for ($dy = -1; $dy <= 1; $dy++) {
                for ($dx = -1; $dx <= 1; $dx++) {
                    if ($dx || $dy || $dz) {
                        $offs[] = $dz * $plane + $dy * $nx + $dx;
                    }
                }
            }
        }
        $levels = 2000;
        $buckets = array_fill(0, $levels + 1, []);
        foreach ($solidIdx as $i) {
            if ($M[$i] > 0) {
                $buckets[(int) ($M[$i] / $Mmax * $levels)][] = $i;
            }
        }
        $lab = array_fill(0, $g->cells(), 0);
        $uf = [0];
        $peakM = [0.0];
        $peakIdx = [0];
        $cnt = [0];
        $death = [];
        $find = function (int $a) use (&$uf): int {
            $r = $a;
            while ($uf[$r] !== $r) {
                $r = $uf[$r];
            }
            while ($uf[$a] !== $r) {
                $nx_ = $uf[$a];
                $uf[$a] = $r;
                $a = $nx_;
            }

            return $r;
        };
        for ($q = $levels; $q >= 0; $q--) {
            foreach ($buckets[$q] as $v) {
                $roots = [];
                foreach ($offs as $o) {
                    $l = $lab[$v + $o];
                    if ($l > 0) {
                        $roots[$find($l)] = true;
                    }
                }
                if (! $roots) {
                    $id = count($uf);
                    $uf[] = $id;
                    $peakM[] = $M[$v];
                    $peakIdx[] = $v;
                    $cnt[] = 1;
                    $lab[$v] = $id;
                    continue;
                }
                $elder = 0;
                foreach (array_keys($roots) as $r) {
                    if ($elder === 0 || $peakM[$r] > $peakM[$elder] || ($peakM[$r] == $peakM[$elder] && $r < $elder)) {
                        $elder = $r;
                    }
                }
                $lab[$v] = $elder;
                $cnt[$elder]++;
                foreach (array_keys($roots) as $r) {
                    if ($r !== $elder) {
                        $uf[$r] = $elder;
                        $death[$r] = [$M[$v], $elder];
                    }
                }
            }
        }
        unset($buckets);

        $okRatio = (float) $cfg['neck_ok_ratio'];
        $severe = (float) $cfg['severe_ratio'];
        $minPeak = $cfg['significant_peak_share'] * $Mmax;
        $minProm = $cfg['min_prominence_voxels'] * $h;
        $total = count($uf);
        $isolated = [];
        $noise = 0;
        for ($c = 1; $c < $total; $c++) {
            if (! isset($death[$c])) {
                continue;   // primary peak of a separate body
            }
            [$saddle, $parent] = $death[$c];
            $peak = $peakM[$c];
            $significant = $peak >= $minPeak && ($peak - $saddle) >= $minProm && $cnt[$c] >= 4;
            if (! $significant) {
                $noise++;
                continue;
            }
            if ($saddle < $okRatio * $peak) {
                $isolated[$c] = true;
            }
        }
        // Each cell belongs to the feed group of the nearest hotspot above it in the merge tree.
        $groupOf = [];
        $resolve = function (int $c) use (&$resolve, &$groupOf, $isolated, $death): int {
            if (isset($groupOf[$c])) {
                return $groupOf[$c];
            }
            if (! isset($death[$c]) || isset($isolated[$c])) {
                return $groupOf[$c] = $c;
            }

            return $groupOf[$c] = $resolve($death[$c][1]);
        };
        $groupVol = [];
        for ($c = 1; $c < $total; $c++) {
            $gId = $resolve($c);
            $groupVol[$gId] = ($groupVol[$gId] ?? 0) + $cnt[$c];
        }

        $heads = [];
        for ($c = 1; $c < $total; $c++) {
            if (! isset($death[$c]) || isset($isolated[$c])) {
                $heads[] = $c;
            }
        }
        usort($heads, fn ($a, $b) => $peakM[$b] <=> $peakM[$a]);
        $list = [];
        $headNo = [];
        foreach ($heads as $n => $c) {
            $headNo[$c] = $n + 1;
        }
        // Hot zone of each hotspot: cells of its feed group within 10 % of its modulus. The deepest of them is its centre.
        $perHot = [];
        foreach ($solidIdx as $i) {
            $l = $lab[$i];
            if ($l === 0) {
                continue;
            }
            $gId = $groupOf[$l] ?? null;
            if ($gId === null || ! isset($headNo[$gId])) {
                continue;
            }
            if ($M[$i] >= 0.9 * $peakM[$gId]) {
                $perHot[$headNo[$gId]][] = $i;
            }
        }
        $solidCells = max(1, $g->solidCount);
        foreach ($heads as $n => $c) {
            $cells = $perHot[$n + 1] ?? [$peakIdx[$c]];
            $idx = $cells[0];
            foreach ($cells as $cell) {
                if ($r2[$cell] > $r2[$idx]) {
                    $idx = $cell;
                }
            }
            $ctr = $g->centre($idx);
            $primary = ! isset($death[$c]);
            $saddle = $primary ? null : $death[$c][0];
            $ratio = $primary ? null : $saddle / $peakM[$c];
            $parent = $primary ? null : ($headNo[$resolve($death[$c][1])] ?? null);
            $list[] = [
                'id' => 'H'.($n + 1),
                'role' => $primary ? 'PRIMARY' : ($ratio < $severe ? 'ISOLATED_SEVERE' : 'ISOLATED'),
                'modulus_cm' => $peakM[$c] / 10,
                'neck_modulus_cm' => $saddle !== null ? $saddle / 10 : null,
                'neck_ratio' => $ratio,
                'inscribed_radius_mm' => (sqrt($r2[$idx]) - 0.5) * $h,
                'centre_mm' => array_map(fn ($v) => round($v, 2), $ctr),
                'group_volume_cm3' => max($groupVol[$c], count($cells)) * $h ** 3 / 1000,
                'volume_share_pct' => max($groupVol[$c], count($cells)) / $solidCells * 100,
                'feeds_through' => $parent !== null ? 'H'.$parent : null,
            ];
        }

        // Point cloud of the hot zones for the 3-D view.
        $cloud = ['hot' => []];
        foreach ($perHot as $no => $cells) {
            $step = max(1, (int) ceil(count($cells) / 1500));
            for ($k = 0; $k < count($cells); $k += $step) {
                $c = $g->centre($cells[$k]);
                $cloud['hot'][] = [round($c[0], 1), round($c[1], 1), round($c[2], 1), $no];
            }
        }

        $primaryCentre = null;
        foreach ($list as $hs) {
            if ($hs['role'] === 'PRIMARY') {
                $primaryCentre = $hs['centre_mm'];
                break;
            }
        }

        return ['list' => $list, 'noise' => $noise, 'cloud' => $cloud, 'primary_centre' => $primaryCentre];
    }

    private function ghostCloud(Grid $g): array
    {
        $n = count($this->S['px']);
        $step = max(1, (int) ceil($n / 5000));
        $out = [];
        for ($i = 0; $i < $n; $i += $step) {
            $out[] = [round($this->S['px'][$i], 1), round($this->S['py'][$i], 1), round($this->S['pz'][$i], 1)];
        }

        return $out;
    }

    // ============================================================ wall thickness
    private function wallThickness(Grid $g, Mesh $mesh): array
    {
        $S = $this->S;
        $h = $g->h;
        $n = count($S['px']);
        $step = max(1, (int) ceil($n / 40000));
        $diag = sqrt(array_sum(array_map(fn ($v) => $v * $v, $mesh->size())));
        $maxSteps = (int) ceil($diag / (0.5 * $h)) + 4;
        $pairs = [];   // [thickness, area, sampleIndex]
        $unresolved = 0.0;
        $totalA = 0.0;
        for ($i = 0; $i < $n; $i += $step) {
            $a = $S['a'][$i] * $step;
            $totalA += $a;
            $dx = -$S['nx'][$i]; $dy = -$S['ny'][$i]; $dz = -$S['nz'][$i];
            $last = -1.0;
            for ($s = 1; $s <= $maxSteps; $s++) {
                $d = $s * 0.5 * $h;
                if ($g->isSolidAt($S['px'][$i] + $dx * $d, $S['py'][$i] + $dy * $d, $S['pz'][$i] + $dz * $d)) {
                    $last = $d;
                } else {
                    break;
                }
            }
            if ($last < 0) {
                $unresolved += $a;
                continue;
            }
            $pairs[] = [$last + 0.25 * $h, $a, $i];
        }
        usort($pairs, fn ($a, $b) => $a[0] <=> $b[0]);
        $resolvedA = array_sum(array_column($pairs, 1));
        $pct = function (float $p) use ($pairs, $resolvedA) {
            if (! $pairs) {
                return 0.0;
            }
            $acc = 0.0;
            foreach ($pairs as [$t, $a]) {
                $acc += $a;
                if ($acc >= $p * $resolvedA) {
                    return $t;
                }
            }

            return end($pairs)[0];
        };
        return [
            'min_mm' => $pairs ? $pairs[0][0] : 0.0,
            'p05_mm' => $pct(0.05), 'p50_mm' => $pct(0.50), 'p95_mm' => $pct(0.95),
            'max_mm' => $pairs ? end($pairs)[0] : 0.0,
            'unresolved_pct' => $totalA > 0 ? $unresolved / $totalA * 100 : 0,
            'resolution_mm' => $h,
            'pairs' => array_map(fn ($p) => [$p[0], $p[1], $p[2]], $pairs),
        ];
    }

    /** Area-weighted share of the surface in each thickness band (bounds in mm; the last band is open). */
    private function histogram(array $thickness): array
    {
        $bounds = [3, 4, 6, 8, 12, 20, 30, 50, 80, 120];
        $bands = array_fill(0, count($bounds) + 1, 0.0);
        $tot = 0.0;
        foreach ($thickness['pairs'] as [$t, $a]) {
            $k = 0;
            while ($k < count($bounds) && $t >= $bounds[$k]) {
                $k++;
            }
            $bands[$k] += $a;
            $tot += $a;
        }
        $out = [];
        foreach ($bands as $k => $a) {
            $out[] = ['from' => $k === 0 ? 0 : $bounds[$k - 1], 'to' => $bounds[$k] ?? null, 'pct' => $tot > 0 ? round($a / $tot * 100, 1) : 0];
        }

        return $out;
    }

    /** Clusters of thin wall under a limit. */
    private function thinZones(array $thickness, float $limitMm, float $binMm): array
    {
        $S = $this->S;
        $bins = [];
        foreach ($thickness['pairs'] as [$t, $a, $i]) {
            if ($t >= $limitMm) {
                break;
            }
            $key = floor($S['px'][$i] / $binMm).','.floor($S['py'][$i] / $binMm).','.floor($S['pz'][$i] / $binMm);
            $b = &$bins[$key];
            $b ??= ['area' => 0.0, 'min' => INF, 'x' => 0.0, 'y' => 0.0, 'z' => 0.0];
            $b['area'] += $a;
            $b['min'] = min($b['min'], $t);
            $b['x'] += $S['px'][$i] * $a; $b['y'] += $S['py'][$i] * $a; $b['z'] += $S['pz'][$i] * $a;
            unset($b);
        }
        $zones = [];
        foreach ($bins as $b) {
            $zones[] = ['area_cm2' => $b['area'] / 100, 'min_mm' => $b['min'], 'centre_mm' => [round($b['x'] / $b['area'], 1), round($b['y'] / $b['area'], 1), round($b['z'] / $b['area'], 1)]];
        }
        usort($zones, fn ($a, $b) => $b['area_cm2'] <=> $a['area_cm2']);

        return array_slice($zones, 0, 6);
    }

    // ============================================================ moulding: parting line, draft, undercuts, cores
    private function mouldingStudy(Mesh $mesh, Grid $g, ?array $hotCentre): array
    {
        $S = $this->S;
        $cfg = config('casting.moulding');
        $n = count($S['px']);
        $totalA = array_sum($S['a']);
        $sinD = sin(deg2rad((float) $cfg['min_draft_deg']));
        $step = max(1, (int) ceil($n / 40000));
        $axes = ['X', 'Y', 'Z'];
        $pos = [$S['px'], $S['py'], $S['pz']];
        $nrm = [$S['nx'], $S['ny'], $S['nz']];
        $best = null;
        $cand = (int) $cfg['parting_candidates'];
        $tried = [];
        for ($a = 0; $a < 3; $a++) {
            $lo = $mesh->min[$a]; $hi = $mesh->max[$a];
            if ($hi - $lo < 1e-6) {
                continue;
            }
            for ($c = 1; $c <= $cand; $c++) {
                $frac = $c / ($cand + 1);
                $p = $lo + ($hi - $lo) * $frac;
                $und = 0.0; $vert = 0.0;
                for ($i = 0; $i < $n; $i += $step) {
                    $nn = $nrm[$a][$i];
                    $w = $S['a'][$i] * $step;
                    if (abs($nn) <= $sinD) {
                        $vert += $w;
                    } elseif (($pos[$a][$i] > $p && $nn < -$sinD) || ($pos[$a][$i] <= $p && $nn > $sinD)) {
                        $und += $w;
                    }
                }
                $score = $und + 0.3 * $vert + 0.02 * abs($frac - 0.5) * $totalA;
                if ($best === null || $score < $best['score']) {
                    $best = ['score' => $score, 'axis' => $a, 'p' => $p, 'frac' => $frac];
                }
            }
        }
        if ($best === null) {
            return ['axis' => 'Z', 'plane_mm' => 0, 'undercut_pct' => 0, 'vertical_pct' => 0, 'zones' => [], 'hot_in_cope' => null, 'flip' => false];
        }
        $a = $best['axis']; $p = $best['p'];
        $und = 0.0; $vert = 0.0;
        $bins = [];
        $ext = max($mesh->size()) / 10;
        for ($i = 0; $i < $n; $i++) {
            $nn = $nrm[$a][$i];
            $w = $S['a'][$i];
            if (abs($nn) <= $sinD) {
                $vert += $w;
            } elseif (($pos[$a][$i] > $p && $nn < -$sinD) || ($pos[$a][$i] <= $p && $nn > $sinD)) {
                $und += $w;
                $key = floor($S['px'][$i] / $ext).','.floor($S['py'][$i] / $ext).','.floor($S['pz'][$i] / $ext);
                $b = &$bins[$key];
                $b ??= ['area' => 0.0, 'x' => 0.0, 'y' => 0.0, 'z' => 0.0];
                $b['area'] += $w; $b['x'] += $S['px'][$i] * $w; $b['y'] += $S['py'][$i] * $w; $b['z'] += $S['pz'][$i] * $w;
                unset($b);
            }
        }
        $zones = [];
        foreach ($bins as $b) {
            if ($b['area'] >= 0.004 * $totalA) {
                $zones[] = ['area_cm2' => $b['area'] / 100, 'centre_mm' => [round($b['x'] / $b['area'], 1), round($b['y'] / $b['area'], 1), round($b['z'] / $b['area'], 1)]];
            }
        }
        usort($zones, fn ($x, $y) => $y['area_cm2'] <=> $x['area_cm2']);
        // Put the main hotspot on top (cope side) where a riser can sit on it.
        $flip = false;
        $hotCope = null;
        if ($hotCentre !== null) {
            $hotCope = $hotCentre[$a] > $p;
            $flip = ! $hotCope;
        }

        return [
            'axis' => $axes[$a], 'axis_index' => $a, 'plane_mm' => round($p, 2), 'plane_frac' => $best['frac'],
            'undercut_pct' => $totalA > 0 ? $und / $totalA * 100 : 0,
            'undercut_area_cm2' => $und / 100,
            'vertical_pct' => $totalA > 0 ? $vert / $totalA * 100 : 0,
            'zones' => array_slice($zones, 0, 6),
            'hot_in_cope' => $hotCope, 'flip' => $flip,
            'height_mm' => $mesh->max[$a] - $mesh->min[$a],
        ];
    }

    // ============================================================ enclosed cavities
    private function closedVoids(Grid $g): array
    {
        $nx = $g->nx; $ny = $g->ny; $nz = $g->nz; $plane = $nx * $ny; $N = $g->cells();
        $vis = $g->solid;   // "\0" empty, "\1" solid; "\2" = reached from outside
        $stack = [0];
        $vis[0] = "\2";
        while ($stack) {
            $i = array_pop($stack);
            $x = $i % $nx; $y = intdiv($i, $nx) % $ny; $z = intdiv($i, $plane);
            if ($x > 0 && $vis[$i - 1] === "\0") { $vis[$i - 1] = "\2"; $stack[] = $i - 1; }
            if ($x < $nx - 1 && $vis[$i + 1] === "\0") { $vis[$i + 1] = "\2"; $stack[] = $i + 1; }
            if ($y > 0 && $vis[$i - $nx] === "\0") { $vis[$i - $nx] = "\2"; $stack[] = $i - $nx; }
            if ($y < $ny - 1 && $vis[$i + $nx] === "\0") { $vis[$i + $nx] = "\2"; $stack[] = $i + $nx; }
            if ($z > 0 && $vis[$i - $plane] === "\0") { $vis[$i - $plane] = "\2"; $stack[] = $i - $plane; }
            if ($z < $nz - 1 && $vis[$i + $plane] === "\0") { $vis[$i + $plane] = "\2"; $stack[] = $i + $plane; }
        }
        $voids = [];
        $h3 = $g->h ** 3;
        for ($i = strpos($vis, "\0"); $i !== false; $i = strpos($vis, "\0", $i + 1)) {
            if (count($voids) >= 20) {
                break;
            }
            $cells = 0; $sx = $sy = $sz = 0.0;
            $stack = [$i];
            $vis[$i] = "\3";
            while ($stack) {
                $j = array_pop($stack);
                $cells++;
                $x = $j % $nx; $y = intdiv($j, $nx) % $ny; $z = intdiv($j, $plane);
                $sx += $x; $sy += $y; $sz += $z;
                foreach ([[$x > 0, -1], [$x < $nx - 1, 1], [$y > 0, -$nx], [$y < $ny - 1, $nx], [$z > 0, -$plane], [$z < $nz - 1, $plane]] as [$ok, $d]) {
                    if ($ok && $vis[$j + $d] === "\0") {
                        $vis[$j + $d] = "\3";
                        $stack[] = $j + $d;
                    }
                }
            }
            if ($cells >= 8) {
                $voids[] = [
                    'volume_cm3' => $cells * $h3 / 1000,
                    'centre_mm' => [round($g->origin[0] + ($sx / $cells + 0.5) * $g->h, 1), round($g->origin[1] + ($sy / $cells + 0.5) * $g->h, 1), round($g->origin[2] + ($sz / $cells + 0.5) * $g->h, 1)],
                ];
            }
        }

        return $voids;
    }
}
