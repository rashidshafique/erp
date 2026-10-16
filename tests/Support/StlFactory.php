<?php

namespace Tests\Support;

/** Builds small closed STL solids with known answers for testing the casting analyser. */
class StlFactory
{
    /** A solid made from filled unit cells [[i,j,k], ...] scaled by $s mm. Faces between filled and empty cells become triangles. */
    public static function cells(array $filled, float $s = 1.0): string
    {
        $set = [];
        foreach ($filled as [$i, $j, $k]) {
            $set["$i,$j,$k"] = true;
        }
        $tris = [];
        $dirs = [[1, 0, 0], [-1, 0, 0], [0, 1, 0], [0, -1, 0], [0, 0, 1], [0, 0, -1]];
        foreach ($filled as [$i, $j, $k]) {
            foreach ($dirs as [$dx, $dy, $dz]) {
                if (isset($set[($i + $dx).','.($j + $dy).','.($k + $dz)])) {
                    continue;
                }
                $c = [$i + ($dx > 0 ? 1 : 0), $j + ($dy > 0 ? 1 : 0), $k + ($dz > 0 ? 1 : 0)];
                // two in-plane axes
                $ax = $dx ? [0, 1, 0] : ([0, 0, 1]);
                $bx = $dx ? [0, 0, 1] : ($dy ? [1, 0, 0] : [0, 1, 0]);
                if ($dy) { $ax = [0, 0, 1]; }
                if ($dz) { $ax = [1, 0, 0]; }
                $p = [
                    $c,
                    [$c[0] + $ax[0], $c[1] + $ax[1], $c[2] + $ax[2]],
                    [$c[0] + $ax[0] + $bx[0], $c[1] + $ax[1] + $bx[1], $c[2] + $ax[2] + $bx[2]],
                    [$c[0] + $bx[0], $c[1] + $bx[1], $c[2] + $bx[2]],
                ];
                // orient so the normal points along (dx,dy,dz)
                $u = [$p[1][0] - $p[0][0], $p[1][1] - $p[0][1], $p[1][2] - $p[0][2]];
                $v = [$p[2][0] - $p[0][0], $p[2][1] - $p[0][1], $p[2][2] - $p[0][2]];
                $n = [$u[1] * $v[2] - $u[2] * $v[1], $u[2] * $v[0] - $u[0] * $v[2], $u[0] * $v[1] - $u[1] * $v[0]];
                if ($n[0] * $dx + $n[1] * $dy + $n[2] * $dz < 0) {
                    $p = [$p[0], $p[3], $p[2], $p[1]];
                }
                $tris[] = [$p[0], $p[1], $p[2]];
                $tris[] = [$p[0], $p[2], $p[3]];
            }
        }

        return self::binary($tris, $s);
    }

    public static function box(int $a, int $b, int $c, float $s = 1.0, array $offset = [0, 0, 0]): array
    {
        $out = [];
        for ($i = 0; $i < $a; $i++) for ($j = 0; $j < $b; $j++) for ($k = 0; $k < $c; $k++) $out[] = [$i + $offset[0], $j + $offset[1], $k + $offset[2]];

        return $out;
    }

    public static function binary(array $tris, float $s = 1.0): string
    {
        $out = str_pad('test', 80, "\0").pack('V', count($tris));
        foreach ($tris as $t) {
            $out .= pack('g3', 0, 0, 0);
            foreach ($t as $p) {
                $out .= pack('g3', $p[0] * $s, $p[1] * $s, $p[2] * $s);
            }
            $out .= "\0\0";
        }

        return $out;
    }

    public static function sphere(float $r, int $seg = 32): string
    {
        $tris = [];
        $pt = fn ($i, $j) => [$r * sin(M_PI * $i / $seg) * cos(2 * M_PI * $j / (2 * $seg)), $r * sin(M_PI * $i / $seg) * sin(2 * M_PI * $j / (2 * $seg)), $r * cos(M_PI * $i / $seg)];
        for ($i = 0; $i < $seg; $i++) {
            for ($j = 0; $j < 2 * $seg; $j++) {
                $a = $pt($i, $j); $b = $pt($i + 1, $j); $c = $pt($i + 1, $j + 1); $d = $pt($i, $j + 1);
                if ($i > 0) $tris[] = [$a, $b, $d];
                if ($i < $seg - 1) $tris[] = [$d, $b, $c];
            }
        }

        return self::binary($tris);
    }
}
