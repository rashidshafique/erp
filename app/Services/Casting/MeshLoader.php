<?php

namespace App\Services\Casting;

/** Reads STL (binary or ASCII) and, through an optional converter, STEP. Everything is returned in millimetres. */
class MeshLoader
{
    public function load(string $path, string $type, float $toMm): Mesh
    {
        if (! is_file($path)) {
            throw new CastingAnalysisException('The CAD file is missing from storage.');
        }
        $type = strtoupper($type);
        $cleanup = null;
        if ($type === 'STEP') {
            $path = $cleanup = $this->convertStep($path);
        }
        try {
            $mesh = $this->readStl($path, $toMm);
        } finally {
            if ($cleanup && is_file($cleanup)) {
                @unlink($cleanup);
            }
        }
        $mesh->format = $type;
        $mesh->finish();
        $mesh->checkClosed();

        return $mesh;
    }

    public static function typeOf(string $path, string $clientName): string
    {
        $ext = strtolower(pathinfo($clientName, PATHINFO_EXTENSION));
        if (in_array($ext, ['stp', 'step'], true)) {
            $head = (string) file_get_contents($path, false, null, 0, 64);
            if (! str_contains($head, 'ISO-10303-21')) {
                throw new CastingAnalysisException('That is not a valid STEP file (it should start with ISO-10303-21).');
            }

            return 'STEP';
        }
        if ($ext === 'stl') {
            return 'STL';
        }
        throw new CastingAnalysisException('Upload an .stl, .stp or .step file.');
    }

    private function readStl(string $path, float $toMm): Mesh
    {
        $size = filesize($path);
        $max = (int) config('casting.max_triangles');
        $fh = fopen($path, 'rb');
        $head = fread($fh, 84);
        fclose($fh);
        if (strlen($head) < 15) {
            throw new CastingAnalysisException('The file is too small to be an STL.');
        }
        $n = strlen($head) >= 84 ? unpack('V', substr($head, 80, 4))[1] : 0;
        $isBinary = $size === 84 + 50 * $n && $n > 0;
        $mesh = new Mesh();
        if ($isBinary) {
            if ($n > $max) {
                throw new CastingAnalysisException("The model has {$n} triangles; the limit is {$max}. Export a coarser mesh.");
            }
            $data = file_get_contents($path);
            $tri = [];
            $off = 84;
            for ($i = 0; $i < $n; $i++, $off += 50) {
                $v = unpack('g12', $data, $off);
                // v[1..3] is the normal; vertices follow.
                for ($k = 4; $k <= 12; $k++) {
                    $tri[] = $v[$k] * $toMm;
                }
            }
            $mesh->tri = $tri;

            return $mesh;
        }
        if (! preg_match('/^\s*solid/i', $head)) {
            throw new CastingAnalysisException('This does not look like an STL file.');
        }
        $text = file_get_contents($path);
        $found = preg_match_all('/vertex\s+([-+0-9.eE]+)\s+([-+0-9.eE]+)\s+([-+0-9.eE]+)/i', $text, $m);
        if (! $found || $found % 3 !== 0) {
            throw new CastingAnalysisException('The ASCII STL could not be read (vertex count is not a multiple of 3).');
        }
        if ($found / 3 > $max) {
            throw new CastingAnalysisException('The model has too many triangles. Export a coarser mesh.');
        }
        $tri = [];
        for ($i = 0; $i < $found; $i++) {
            $tri[] = (float) $m[1][$i] * $toMm;
            $tri[] = (float) $m[2][$i] * $toMm;
            $tri[] = (float) $m[3][$i] * $toMm;
        }
        $mesh->tri = $tri;

        return $mesh;
    }

    /** Runs the configured STEP→STL helper on temporary files. File names from the user never reach the command line. */
    private function convertStep(string $in): string
    {
        $cmd = (string) config('casting.step_converter');
        if ($cmd === '' || ! str_contains($cmd, '{in}') || ! str_contains($cmd, '{out}')) {
            throw new CastingAnalysisException('STEP files need a converter that is not set up. Export the part as STL from your CAD system, or see tools/step_to_stl.py.');
        }
        $tmpIn = tempnam(sys_get_temp_dir(), 'fstp').'.step';
        $tmpOut = tempnam(sys_get_temp_dir(), 'fstl').'.stl';
        copy($in, $tmpIn);
        $full = str_replace(['{in}', '{out}'], [escapeshellarg($tmpIn), escapeshellarg($tmpOut)], $cmd);
        $proc = @proc_open($full, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($proc)) {
            @unlink($tmpIn);
            throw new CastingAnalysisException('The STEP converter could not be started. Export the part as STL instead.');
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $deadline = microtime(true) + (int) config('casting.step_timeout_seconds');
        $err = '';
        while (true) {
            $st = proc_get_status($proc);
            $err .= (string) stream_get_contents($pipes[2]);
            stream_get_contents($pipes[1]);
            if (! $st['running']) {
                break;
            }
            if (microtime(true) > $deadline) {
                proc_terminate($proc, 9);
                @unlink($tmpIn);
                @unlink($tmpOut);
                throw new CastingAnalysisException('The STEP conversion took too long. Export the part as STL instead.');
            }
            usleep(100000);
        }
        $code = $st['exitcode'];
        proc_close($proc);
        @unlink($tmpIn);
        if ($code !== 0 || ! is_file($tmpOut) || filesize($tmpOut) < 84) {
            @unlink($tmpOut);
            $why = str_contains($err, 'cadquery') ? ' (the converter needs: pip install cadquery)' : '';
            throw new CastingAnalysisException('The STEP file could not be converted'.$why.'. Export the part as STL from your CAD system instead.');
        }

        return $tmpOut;
    }
}
