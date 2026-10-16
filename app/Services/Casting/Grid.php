<?php

namespace App\Services\Casting;

/** A regular voxel grid. Index = (z * ny + y) * nx + x. Cell centres sit at origin + (i + 0.5) * h. */
class Grid
{
    public int $nx;
    public int $ny;
    public int $nz;
    public float $h;
    public array $origin;
    /** One byte per cell: "\1" solid, "\0" empty. */
    public string $solid;
    public int $solidCount = 0;
    public int $oddColumns = 0;
    public int $hitColumns = 0;

    public function cells(): int
    {
        return $this->nx * $this->ny * $this->nz;
    }

    public function centre(int $idx): array
    {
        $x = $idx % $this->nx;
        $y = intdiv($idx, $this->nx) % $this->ny;
        $z = intdiv($idx, $this->nx * $this->ny);

        return [$this->origin[0] + ($x + 0.5) * $this->h, $this->origin[1] + ($y + 0.5) * $this->h, $this->origin[2] + ($z + 0.5) * $this->h];
    }

    public function indexAt(float $px, float $py, float $pz): int
    {
        $x = (int) floor(($px - $this->origin[0]) / $this->h);
        $y = (int) floor(($py - $this->origin[1]) / $this->h);
        $z = (int) floor(($pz - $this->origin[2]) / $this->h);
        if ($x < 0 || $y < 0 || $z < 0 || $x >= $this->nx || $y >= $this->ny || $z >= $this->nz) {
            return -1;
        }

        return ($z * $this->ny + $y) * $this->nx + $x;
    }

    public function isSolidAt(float $px, float $py, float $pz): bool
    {
        $i = $this->indexAt($px, $py, $pz);

        return $i >= 0 && $this->solid[$i] === "\1";
    }
}
