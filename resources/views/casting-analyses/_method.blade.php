<div class="panel">
    <div class="panel-header"><h2>Step by step</h2></div>
    <div class="panel-body">
        <ol style="margin:0 0 0 20px;padding:0">
            <li style="margin-bottom:10px"><strong>Read and check the model.</strong> {{ $res['triangles'] }} triangles, size {{ implode(' × ', array_map(fn ($v) => $n($v, 1), $res['size_mm'])) }} mm, volume {{ $n($res['volume']['mesh_cm3'], 1) }} cm³, surface {{ $n($res['area_cm2'], 0) }} cm². Then the part is filled with {{ \App\Support\Formats::number($res['grid']['solid_cells']) }} cells of {{ $n($res['grid']['h_mm'], 2) }} mm (volume within {{ $n(abs($res['volume']['error_pct']), 1) }}% of the mesh).</li>
            <li style="margin-bottom:10px"><strong>Measure the section modulus everywhere.</strong> Each cell is grouped with the centre line or plane of its own section. The volume and the mould-contact surface around that centre give M = V ÷ A. Reference values reproduced: cube a/6, sphere R/3, plate T/2.</li>
            <li style="margin-bottom:10px"><strong>Follow the modulus to find the last places to freeze.</strong> Metal freezes in order of modulus. Starting from the heaviest section and walking down, each separate peak is a hotspot and the lowest point on the way to a heavier one is its neck. {{ count($res['hotspots']) }} hotspot(s) were kept; {{ $res['noise_peaks'] }} tiny bumps were ignored as numerical noise.</li>
            @foreach ($rec['steps'] as $s)
                <li style="margin-bottom:10px"><strong>{{ $s['title'] }}.</strong> {{ $s['text'] }}</li>
            @endforeach
        </ol>
    </div>
</div>

<div class="panel">
    <div class="panel-header"><h2>What this does and does not do</h2></div>
    <div class="panel-body">
        <p style="margin-top:0">This is a fast geometric study based on the modulus method that foundries use for risering by hand. It is good at ranking sections, finding where shrinkage will want to form and sizing a first feeding layout. It is <strong>not</strong> a full thermal and flow simulation.</p>
        <ul style="margin:0 0 0 18px">
            <li>Not covered: mould filling, gas and sand defects, inclusions, hot tears, distortion and residual stress.</li>
            <li>Freezing times use a single constant B per iron family. Calibrate B against your own pours before reading the minutes literally; the ranking of hotspots does not depend on it.</li>
            <li>Mould, chill and feeder effects are rule-of-thumb factors from <code>config/casting.php</code>. Adjust them to your plant practice.</li>
            <li>Chemistry windows are section-driven starting points. Your spectrometer, thermal analysis and the customer specification decide the final heat.</li>
            <li>Confidence for this run: <strong>{{ strtolower($res['quality']['confidence']) }}</strong>; cell size {{ $n($res['quality']['cell_mm'], 2) }} mm{{ $res['quality']['refined'] ? ' (refined automatically for thin walls)' : '' }}.</li>
        </ul>
    </div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Wall thickness distribution</h2><span class="muted small">share of surface area</span></div>
    <div class="table-wrap"><table class="no-row-click ca-wrap">
        <thead><tr><th>Wall (mm)</th><th style="width:60%">Share</th></tr></thead>
        <tbody>@foreach ($res['thickness']['hist'] as $b)
            <tr><td>{{ $b['to'] === null ? '≥ '.$b['from'] : $b['from'].' – '.$b['to'] }}</td><td><div style="background:#e8ece9;border-radius:4px;height:10px"><div style="background:var(--accent);height:10px;border-radius:4px;width:{{ $b['pct'] }}%"></div></div> <span class="small">{{ $b['pct'] }}%</span></td></tr>
        @endforeach</tbody>
    </table></div>
</div>
