<?php

namespace App\Services\Casting;

/**
 * Turns the geometric study into decisions: which iron family, a manufacturability score, feeders, chills, gating,
 * moulding and a melt chemistry. Rules are the modulus method and common ferrous practice; every constant lives in
 * config/casting.php so the plant can tune them. Figures for Mg fade, free Mg and base sulphur come from the
 * project's ductile iron material (fade 0.001 % Mg per minute in the pouring ladle; free Mg = Mg - 0.75 S; S < 0.025 %).
 */
class Recommender
{
    /**
     * @param  array  $r  result of SolidificationAnalyzer::analyze
     * @param  array  $meta  size_mm [x,y,z], volume_cm3, nominal_weight_kg
     * @param  array  $opt  family (GREY|DUCTILE|AUTO), grade (array|null), min_tensile, min_elongation, pour_window_min,
     *                      feeder (AUTO|CONVENTIONAL|INSULATED|EXOTHERMIC), b_override, grades (list of candidate grade arrays)
     */
    public function build(array $r, array $meta, array $opt): array
    {
        $steps = [];
        $mod = $r['modulus'];
        $wall = $r['thickness'];
        $hot = $r['hotspots'];
        $mould = $r['moulding'];
        $primary = collect($hot)->firstWhere('role', 'PRIMARY') ?? $hot[0];
        $isolated = array_values(array_filter($hot, fn ($h) => $h['role'] !== 'PRIMARY'));

        // ---- 1. family -----------------------------------------------------------------------------------
        [$family, $why] = $this->chooseFamily($opt, $wall, $isolated);
        $F = config("casting.families.$family");
        $B = (float) ($opt['b_override'] ?? 0) ?: (float) $F['chvorinov_b'];
        $steps[] = ['title' => 'Material route', 'text' => "{$F['label']} chosen. $why"];

        // ---- 2. weight -----------------------------------------------------------------------------------
        $castKg = $meta['volume_cm3'] * $F['density'] / 1000;
        $weightNote = null;
        if (! empty($meta['nominal_weight_kg']) && $meta['nominal_weight_kg'] > 0) {
            $ratio = $castKg / $meta['nominal_weight_kg'];
            if ($ratio > 1.25 || $ratio < 0.8) {
                $weightNote = sprintf('The model weighs %.2f kg but the product says %.2f kg (x%.2f). Check the units chosen for the file and that the model has no extra bodies.', $castKg, $meta['nominal_weight_kg'], $ratio);
            }
        }
        $steps[] = ['title' => 'Weight check', 'text' => sprintf('Model volume %.1f cm3 x density %.2f g/cm3 = %.2f kg.', $meta['volume_cm3'], $F['density'], $castKg).($weightNote ? ' '.$weightNote : '')];

        // ---- 3. solidification times --------------------------------------------------------------------------
        $times = [];
        foreach ($hot as $h) {
            $times[$h['id']] = $B * $h['modulus_cm'] ** 2;
        }
        $tPart = $B * $mod['part_cm'] ** 2;
        $steps[] = ['title' => 'Solidification times (Chvorinov t = B x M2)', 'text' => sprintf(
            'With B = %.2f min/cm2: the casting as a whole freezes in about %.1f min (M = %.2f cm). The last place to freeze, %s, has M = %.2f cm and needs about %.1f min. That is %.1fx the average section, so feeding must outlast it.',
            $B, $tPart, $mod['part_cm'], $primary['id'], $primary['modulus_cm'], $times[$primary['id']], $primary['modulus_cm'] / max($mod['part_cm'], 1e-9)
        ).' Times are relative guides until B is calibrated on your own pours.'];

        // ---- 4. feeding ---------------------------------------------------------------------------------------
        $feederPref = $opt['feeder'] ?? 'AUTO';
        $feeders = [];
        $chills = [];
        $designChanges = [];
        $totalFeederCm3 = 0.0;
        foreach ($hot as $h) {
            $type = $feederPref !== 'AUTO' ? $feederPref : ($h['modulus_cm'] >= 2.5 ? 'EXOTHERMIC' : ($h['modulus_cm'] >= 0.8 ? 'INSULATED' : 'CONVENTIONAL'));
            $fd = config("casting.feeders.$type");
            $f = $this->feederFor($h, $F, $fd, $type);
            $f['position'] = $this->position($h['centre_mm'], $mould);
            $f['optional'] = $family === 'GREY' && $h['role'] === 'PRIMARY' && $h['modulus_cm'] < 1.2;
            $feeders[] = $f;
            $totalFeederCm3 += $f['volume_cm3'];

            if ($h['role'] !== 'PRIMARY') {
                $thick = $h['inscribed_radius_mm'] * 2;
                $factor = min(1.0, ($h['neck_modulus_cm'] / config('casting.modulus.neck_ok_ratio')) / $h['modulus_cm']);
                $chills[] = [
                    'hotspot' => $h['id'],
                    'thickness_mm' => (int) max(8, min(60, round(0.6 * $thick))),
                    'area_cm2' => round(max(25, ($thick * 2) ** 2 / 100), 0),
                    'material' => $h['modulus_cm'] > 1.5 ? 'Steel or cast iron block (internal/external)' : 'Steel or cast iron plate',
                    'effect' => 'Cuts the effective modulus by roughly a quarter to a third, so the neck stays liquid longer than the hotspot.',
                    'caution' => $family === 'DUCTILE' ? 'Chills in ductile iron can leave carbide or fine-grained skin. Keep them thin, inoculate well and check the first castings.' : 'Keep chilled depth within machining allowance.',
                ];
                $designChanges[] = sprintf('Hotspot %s (M %.2f cm) is joined through a neck of M %.2f cm (%.0f%% of its own), so the neck freezes first and cuts it off. Options: feed it directly (below), chill it, or thin it from about %.0f mm to about %.0f mm, or core out a pocket, and blend it into the neighbouring wall with generous radii.',
                    $h['id'], $h['modulus_cm'], $h['neck_modulus_cm'], $h['neck_ratio'] * 100, $thick, max(4, $thick * $factor));
            }
        }
        $nIso = count($isolated);
        $steps[] = ['title' => 'Feeding path check', 'text' => $nIso === 0
            ? sprintf('Only one last-freezing zone (%s at M %.2f cm) and every route to it gets hotter, so one feeder on that zone can feed the whole casting.', $primary['id'], $primary['modulus_cm'])
            : sprintf('%d zone(s) are cut off: the metal path to them freezes before they do (neck modulus below %d%% of the hotspot). Each needs its own feeder or chill, or a design change. Worst: %s at %.0f%% neck ratio.',
                $nIso, round(config('casting.modulus.neck_ok_ratio') * 100), collect($isolated)->sortBy('neck_ratio')->first()['id'], collect($isolated)->min('neck_ratio') * 100)];

        // ---- 5. walls ------------------------------------------------------------------------------------------
        $adv = (float) $F['wall_advised_min_mm'];
        $hard = (float) $F['wall_hard_min_mm'];
        $thin = $wall['thin_zones'] ?? [];
        $steps[] = ['title' => 'Wall thickness', 'text' => sprintf(
            'Thinnest wall (5th percentile by area) %.1f mm, typical %.1f mm. %s sand casting wants at least %.0f mm (absolute minimum %.0f mm). Measured to within about %.1f mm (voxel size).',
            $wall['p05_mm'], $wall['p50_mm'], $F['label'], $adv, $hard, $wall['resolution_mm']
        )];

        // ---- 6. moulding ---------------------------------------------------------------------------------------
        $voidCount = count($r['voids']);
        $cores = [];
        foreach ($mould['zones'] as $i => $z) {
            $cores[] = ['label' => 'Undercut zone '.($i + 1), 'centre_mm' => $z['centre_mm'], 'area_cm2' => $z['area_cm2'], 'advice' => 'Needs a core (or a loose piece / change of parting) because the face looks away from the pull direction.'];
        }
        foreach ($r['voids'] as $i => $v) {
            $cores[] = ['label' => 'Closed cavity '.($i + 1), 'centre_mm' => $v['centre_mm'], 'area_cm2' => null, 'advice' => sprintf('%.0f cm3 fully enclosed. Needs a core with prints, or open the cavity with a hole (at least about 10 mm) so the core can be supported and gas can leave.', $v['volume_cm3'])];
        }
        $steps[] = ['title' => 'Parting line and cores', 'text' => sprintf(
            'Best split is across the %s axis at %.0f mm: %.1f%% of the surface looks the wrong way (undercut) and %.1f%% is parallel to the pull and needs pattern draft. %s',
            $mould['axis'], $mould['plane_mm'], $mould['undercut_pct'], $mould['vertical_pct'],
            $mould['hot_in_cope'] === null ? '' : ($mould['hot_in_cope'] ? 'The main hotspot is in the cope half, so the riser can sit on top.' : 'The main hotspot is in the drag half: turn the pattern over so it ends up on top (flip), or feed it from the side.')
        ).($voidCount ? " $voidCount closed cavity found." : '')];

        // ---- 7. gating and yield ----------------------------------------------------------------------------------
        $feederKg = $totalFeederCm3 * $F['density'] / 1000;
        $gatingKg = 0.10 * $castKg;
        $pouredKg = $castKg + $feederKg + $gatingKg;
        $yield = $castKg / max($pouredKg, 1e-9) * 100;
        $gating = $this->gating($F, $pouredKg, $wall, $mould, $hot);
        $steps[] = ['title' => 'Yield and gating', 'text' => sprintf(
            'Casting %.1f kg + feeders %.1f kg + gating (10%%) %.1f kg = %.1f kg poured. Yield about %.0f%%. Pouring time about %.0f s; choke area about %.1f cm2.',
            $castKg, $feederKg, $gatingKg, $pouredKg, $yield, $gating['pour_time_s'], $gating['choke_area_cm2']
        )];

        // ---- 8. score -------------------------------------------------------------------------------------------------
        $score = $this->score($family, $F, $hot, $isolated, $mod, $wall, $mould, $voidCount, $yield, $adv, $hard);
        $steps[] = ['title' => 'Manufacturability score', 'text' => sprintf('Weighted score %d / 100 (%s): %s.', $score['total'], $score['letter'], $score['verdict'])];

        // ---- 9. chemistry -----------------------------------------------------------------------------------------------
        $melt = $this->melt($family, $F, $primary['modulus_cm'], $wall, $opt, $times[$primary['id']]);
        $steps[] = ['title' => 'Chemistry and treatment', 'text' => $melt['summary']];

        // ---- tooling summary ------------------------------------------------------------------------------------------------
        $tooling = $this->tooling($F, $family, $castKg, $meta['size_mm'], $mould, $cores);

        $findings = $this->findings($family, $F, $primary, $isolated, $wall, $mould, $voidCount, $adv, $hard, $r, $weightNote, $yield, $thin);

        return [
            'family' => $family, 'family_label' => $F['label'], 'family_reason' => $why,
            'b' => $B, 'weight' => ['casting_kg' => $castKg, 'feeders_kg' => $feederKg, 'gating_kg' => $gatingKg, 'poured_kg' => $pouredKg, 'yield_pct' => $yield, 'note' => $weightNote],
            'times' => ['part_min' => $tPart, 'hotspots_min' => $times],
            'score' => $score,
            'findings' => $findings,
            'design_changes' => $designChanges,
            'feeders' => $feeders, 'chills' => $chills, 'cores' => $cores,
            'gating' => $gating, 'tooling' => $tooling, 'melt' => $melt,
            'steps' => $steps,
        ];
    }

    // =========================================================================================== family
    private function chooseFamily(array $opt, array $wall, array $isolated): array
    {
        $grade = $opt['grade'] ?? null;
        if ($grade) {
            if (! in_array($grade['family'], ['GREY', 'DUCTILE'], true)) {
                throw new CastingAnalysisException("Grade {$grade['code']} is {$grade['family']} iron. The analyser covers grey and ductile iron.");
            }

            return [$grade['family'], "Taken from the selected grade {$grade['code']}."];
        }
        $want = $opt['family'] ?? 'AUTO';
        if (in_array($want, ['GREY', 'DUCTILE'], true)) {
            return [$want, 'Selected by you.'];
        }
        $el = (float) ($opt['min_elongation'] ?? 0);
        $ts = (float) ($opt['min_tensile'] ?? 0);
        if ($el >= 2 || $ts >= 400) {
            return ['DUCTILE', sprintf('Requirement of %s%s needs the strength or elongation of nodular graphite; grey iron cannot supply it.', $ts ? "{$ts} MPa tensile" : '', $el ? ($ts ? ' and ' : '')."{$el}% elongation" : '')];
        }
        if ($wall['p05_mm'] < 5 || count($isolated) >= 2) {
            return ['GREY', 'No strength or elongation requirement was given. Thin walls and several cut-off hotspots favour grey iron: it flows better and its graphite expansion feeds part of its own shrinkage.'];
        }

        return ['GREY', 'No strength or elongation requirement was given, so the lower-cost, easier-to-cast grey iron is the default. Choose ductile if you need elongation or higher strength.'];
    }

    // =========================================================================================== feeders
    private function feederFor(array $h, array $F, array $fd, string $type): array
    {
        $mc = $h['modulus_cm'];
        $margin = (float) ($F['feeder_modulus_margin'] ?? config('casting.feeders.modulus_margin'));
        $hd = (float) config('casting.feeders.height_to_diameter');
        // M = V/A = (pi/4 D^2 H) / (pi D H + pi/4 D^2) with H = hd*D  ->  D*hd/4 / (hd + 0.25)
        $k = ($hd / 4) / ($hd + 0.25);
        $mf = $margin * $mc;
        $dMod = $mf / ($k * $fd['mef']);
        $demand = $F['feed_demand_pct'] / 100 * $h['group_volume_cm3'] / $fd['efficiency'];
        $dVol = pow($demand / (M_PI / 4 * $hd), 1 / 3);
        $d = max($dMod, $dVol);
        $vol = M_PI / 4 * $d ** 2 * ($hd * $d);
        $mn = (float) config('casting.feeders.neck_margin') * $mc;

        return [
            'hotspot' => $h['id'], 'role' => $h['role'], 'type' => $type, 'type_label' => $fd['label'],
            'diameter_mm' => round($d * 10), 'height_mm' => round($d * $hd * 10),
            'volume_cm3' => $vol, 'weight_kg' => $vol * $F['density'] / 1000,
            'governs' => $dVol > $dMod ? 'feeding volume' : 'modulus',
            'feeder_modulus_cm' => $k * $fd['mef'] * $d,
            'neck_modulus_cm' => $mn, 'neck_diameter_mm' => round(4 * $mn * 10),
            'casting_modulus_cm' => $mc, 'margin' => $margin,
        ];
    }

    private function position(array $c, array $mould): string
    {
        $a = $mould['axis_index'] ?? 2;
        if (($mould['hot_in_cope'] ?? null) === true) {
            return 'Cope side: place the feeder on top of the hotspot.';
        }
        if (($mould['hot_in_cope'] ?? null) === false) {
            return 'Drag side: flip the pattern so it sits on top, or use a side feeder.';
        }

        return 'Place on top of the hotspot.';
    }

    // =========================================================================================== gating
    private function gating(array $F, float $pouredKg, array $wall, array $mould, array $hot): array
    {
        $k = (float) $F['pour_k'];
        $k *= $wall['p05_mm'] < 8 ? 0.75 : ($wall['p50_mm'] > 25 ? 1.25 : 1.0);
        $t = max(3.0, min(180.0, $k * sqrt($pouredKg)));
        $head = (($mould['height_mm'] ?? 100) + 100) / 1000;       // m, top gating
        $v = 0.8 * sqrt(2 * 9.81 * max($head, 0.1));                 // m/s, discharge coefficient 0.8
        $areaCm2 = ($pouredKg / $t) / ($F['density'] * 1000 / 1) / $v * 1e4;   // kg/s / (kg/m3) / (m/s) -> m2 -> cm2
        $ratio = $F['gating_ratio'];
        $chokeVal = $F['choke_at'] === 'ingate' ? $ratio[2] : $ratio[0];
        $unit = $areaCm2 / $chokeVal;

        return [
            'pour_time_s' => $t, 'choke_area_cm2' => $areaCm2, 'effective_head_mm' => $head * 1000,
            'ratio_label' => implode(' : ', $ratio).' ('.$F['gating_note'].')',
            'sprue_area_cm2' => $unit * $ratio[0], 'runner_area_cm2' => $unit * $ratio[1], 'ingate_area_cm2' => $unit * $ratio[2],
            'filter' => $F['filter'] ? sprintf('Use a ceramic foam filter in the runner (size to the filter maker\'s flow rating; at least about %.0f cm2 open area) to hold back Mg-treatment dross.', max(10, 3 * $areaCm2)) : 'Filter optional; a skim core or runner extension is usually enough.',
        ];
    }

    // =========================================================================================== tooling
    private function tooling(array $F, string $family, float $castKg, array $size, array $mould, array $cores): array
    {
        $maxDim = max($size);
        $heavy = $castKg > 150 || $maxDim > 700;
        $allow = $maxDim <= 100 ? 2.5 : ($maxDim <= 250 ? 3.5 : ($maxDim <= 630 ? 4.5 : ($maxDim <= 1000 ? 6.0 : 8.0)));
        $margin = $castKg < 50 ? 60 : ($castKg < 200 ? 80 : 120);
        $h = $mould['height_mm'] ?? $size[2];

        return [
            'process' => $heavy ? 'Resin-bonded (no-bake) sand' : 'Green sand, machine moulded',
            'process_why' => $heavy ? 'Weight or size is beyond what a green-sand flask handles well, and a rigid mould helps feeding.' : 'Weight and size suit a green-sand line; a rigid, well-rammed mould also helps feeding.',
            'pattern' => $castKg <= 30 ? 'Pattern plate (aluminium or cast iron) for series; resin or wood for prototypes.' : 'Loose pattern (wood or resin) for small batches; metal-faced pattern for series.',
            'shrink_allowance_pct' => $F['shrink_allow_pct'],
            'machining_allowance_mm' => $allow,
            'draft' => 'Add 1 to 2 degrees to outside faces and 2 to 3 degrees to cored faces. '.sprintf('%.0f%% of the surface is parallel to the pull and has no draft in the model.', $mould['vertical_pct']),
            'parting' => sprintf('Across %s at %.0f mm%s.', $mould['axis'], $mould['plane_mm'], ($mould['flip'] ?? false) ? ' (turn the model over so the main hotspot is in the cope)' : ''),
            'core_count' => count($cores),
            'core_binder' => count($cores) ? ($family === 'DUCTILE' ? 'Cold-box or shell cores; keep sulphur-bearing catalysts and nitrogen-rich binders low in ductile iron (surface graphite and pinholes).' : 'Cold-box, shell or oil-sand cores to suit volume.') : 'None needed.',
            'flask_mm' => [(int) ceil(($size[0] + 2 * $margin) / 10) * 10, (int) ceil(($size[1] + 2 * $margin) / 10) * 10, (int) ceil(($h + 2 * $margin + 100) / 10) * 10],
            'flask_note' => 'Inside size with sand margin; cope and drag each about half the height plus feeder space.',
        ];
    }

    // =========================================================================================== score
    private function score(string $family, array $F, array $hot, array $isolated, array $mod, array $wall, array $mould, int $voids, float $yield, float $adv, float $hard): array
    {
        $w = config('casting.weights');
        $parts = [];

        $pen = 0;
        $why = [];
        foreach ($isolated as $h) {
            // The further the neck falls below the hotspot, the earlier it freezes and the worse the shrinkage.
            $sev = (float) config('casting.modulus.severe_ratio');
            $okr = (float) config('casting.modulus.neck_ok_ratio');
            $p = $h['neck_ratio'] < $sev
                ? 30 + 30 * ($sev - $h['neck_ratio']) / $sev
                : 12 + 18 * ($okr - $h['neck_ratio']) / max($okr - $sev, 1e-9);
            $p = (int) round($p);
            $pen += $p;
            $why[] = sprintf('%s cut off (neck %.0f%% of its modulus): -%d', $h['id'], $h['neck_ratio'] * 100, $p);
        }
        $q = $mod['max_cm'] / max($mod['p50_cm'], 1e-9);
        $q_pen = $q > 3 ? 25 : ($q > 2.2 ? 15 : ($q > 1.6 ? 8 : 0));
        if ($q_pen) {
            $pen += $q_pen;
            $why[] = sprintf('Heaviest section is %.1fx the typical one: -%d', $q, $q_pen);
        }
        if ($family === 'GREY') {
            $pen *= 0.6;
            $why[] = 'Grey iron self-feeds partly (graphite expansion): penalties x0.6';
        }
        $parts['feeding'] = ['score' => max(0, 100 - $pen), 'why' => $why ?: ['One last-freezing zone and no cut-off sections.']];

        $p05 = $wall['p05_mm'];
        $s = $p05 >= $adv ? 100 : ($p05 >= $hard ? 40 + 60 * ($p05 - $hard) / max($adv - $hard, 1e-9) : max(0, 40 * $p05 / max($hard, 1e-9)));
        $thinShare = 0;
        foreach ($wall['hist'] as $b) {
            if (($b['to'] ?? INF) <= $adv) {
                $thinShare += $b['pct'];
            }
        }
        $s = max(0, $s - min(30, 0.5 * $thinShare));
        $parts['walls'] = ['score' => $s, 'why' => [sprintf('Thinnest wall %.1f mm against %.0f mm advised; %.0f%% of the surface is on thinner wall.', $p05, $adv, $thinShare)]];

        $u = max(0, min(100, 100 * (3.5 - $q) / (3.5 - 1.3)));
        $parts['uniformity'] = ['score' => $u, 'why' => [sprintf('Largest section modulus is %.2fx the typical section (100 points at 1.3x or less, 0 at 3.5x).', $q)]];

        $m = 100 - min(70, $mould['undercut_pct'] * 2.8) - min(70, 35 * $voids);
        $parts['moulding'] = ['score' => max(0, $m), 'why' => [sprintf('%.1f%% undercut surface; %d closed cavit%s.', $mould['undercut_pct'], $voids, $voids === 1 ? 'y' : 'ies')]];

        $parts['yield'] = ['score' => max(0, min(100, 100 * ($yield - 35) / 30)), 'why' => [sprintf('Estimated yield %.0f%% (0 points at 35%%, 100 at 65%%).', $yield)]];

        $tot = 0;
        $sum = 0;
        foreach ($parts as $k => &$p) {
            $p['score'] = round($p['score']);
            $p['weight'] = $w[$k];
            $tot += $p['score'] * $w[$k];
            $sum += $w[$k];
        }
        unset($p);
        $total = (int) round($tot / max($sum, 1));
        // A casting with a cut-off hotspot is never "routine", however good the rest looks.
        $cap = collect($isolated)->contains('role', 'ISOLATED_SEVERE') ? 69 : ($isolated ? 84 : 100);
        if ($total > $cap) {
            $total = $cap;
            $parts['feeding']['why'][] = "Overall score limited to $cap because a hotspot is cut off from feed metal.";
        }
        [$letter, $verdict] = match (true) {
            $total >= 85 => ['A', 'routine casting, standard tooling'],
            $total >= 70 => ['B', 'castable with normal feeders and care'],
            $total >= 55 => ['C', 'difficult: plan chills or extra feeders and review the design'],
            $total >= 40 => ['D', 'high scrap risk: design changes strongly advised'],
            default => ['E', 'redesign before tooling is made'],
        };

        return ['total' => $total, 'letter' => $letter, 'verdict' => $verdict, 'parts' => $parts];
    }

    // =========================================================================================== findings
    private function findings(string $family, array $F, array $primary, array $isolated, array $wall, array $mould, int $voids, float $adv, float $hard, array $r, ?string $weightNote, float $yield, array $thin): array
    {
        $f = [];
        foreach ($isolated as $h) {
            $f[] = ['severity' => $h['role'] === 'ISOLATED_SEVERE' ? 'HIGH' : 'MEDIUM', 'area' => 'Shrinkage', 'text' => sprintf('Hotspot %s near (%s) mm freezes after the metal path to it has closed (neck %.0f%% of its modulus). Shrinkage porosity likely without its own feeder or chill.', $h['id'], implode(', ', $h['centre_mm']), $h['neck_ratio'] * 100)];
        }
        $f[] = ['severity' => 'INFO', 'area' => 'Shrinkage', 'text' => sprintf('Main hotspot %s near (%s) mm, modulus %.2f cm: put the primary feeder here.', $primary['id'], implode(', ', $primary['centre_mm']), $primary['modulus_cm'])];
        if ($wall['p05_mm'] < $hard) {
            $f[] = ['severity' => 'HIGH', 'area' => 'Fluidity', 'text' => sprintf('Walls down to %.1f mm are below the %.0f mm minimum for %s: misruns and cold shuts are likely. Thicken or raise pouring temperature and use a hot, fast gating system.', $wall['p05_mm'], $hard, $F['label'])];
        } elseif ($wall['p05_mm'] < $adv) {
            $f[] = ['severity' => 'MEDIUM', 'area' => 'Fluidity', 'text' => sprintf('Walls of %.1f mm are below the advised %.0f mm for %s. Pour hot and fast, and avoid long thin flow paths.', $wall['p05_mm'], $adv, $F['label'])];
        }
        foreach ($wall['thin_zones'] ?? [] as $z) {
            $f[] = ['severity' => 'MEDIUM', 'area' => 'Fluidity', 'text' => sprintf('Thin region (down to %.1f mm, about %.0f cm2) near (%s) mm.', $z['min_mm'], $z['area_cm2'], implode(', ', $z['centre_mm']))];
            break;
        }
        if ($mould['undercut_pct'] > 2) {
            $f[] = ['severity' => $mould['undercut_pct'] > 10 ? 'HIGH' : 'MEDIUM', 'area' => 'Moulding', 'text' => sprintf('%.1f%% of the surface cannot be drawn from a flat split. Add cores or loose pieces, or reshape.', $mould['undercut_pct'])];
        }
        if ($voids) {
            $f[] = ['severity' => 'HIGH', 'area' => 'Moulding', 'text' => "$voids fully enclosed cavity: it needs a core with prints, which a closed cavity cannot have. Open it with a hole or reconsider the shape."];
        }
        if ($r['volume']['error_pct'] > 5 || $r['volume']['error_pct'] < -5) {
            $f[] = ['severity' => 'MEDIUM', 'area' => 'Accuracy', 'text' => sprintf('Voxel volume differs from the mesh by %.1f%%. Re-run at Fine quality.', $r['volume']['error_pct'])];
        }
        if ($yield < 45) {
            $f[] = ['severity' => 'MEDIUM', 'area' => 'Cost', 'text' => sprintf('Estimated yield is only %.0f%%: feeders weigh a lot. Chills, insulating sleeves or a design change would raise it.', $yield)];
        }
        if ($weightNote) {
            $f[] = ['severity' => 'HIGH', 'area' => 'Units', 'text' => $weightNote];
        }
        usort($f, fn ($a, $b) => ['HIGH' => 0, 'MEDIUM' => 1, 'LOW' => 2, 'INFO' => 3][$a['severity']] <=> ['HIGH' => 0, 'MEDIUM' => 1, 'LOW' => 2, 'INFO' => 3][$b['severity']]);

        return $f;
    }

    // =========================================================================================== melt
    private function melt(string $family, array $F, float $mHeavy, array $wall, array $opt, float $tSolMin): array
    {
        $thinWall = $wall['p05_mm'];
        $tier = $mHeavy < 0.5 ? 0 : ($mHeavy < 1.5 ? 1 : ($mHeavy < 2.5 ? 2 : 3));
        $rows = [];
        $notes = [];
        $window = (float) ($opt['pour_window_min'] ?? 10);

        if ($family === 'GREY') {
            $ce = [[4.4, 4.6], [4.2, 4.4], [4.0, 4.2], [3.8, 4.0]][$tier];
            if ($thinWall < 6) {
                $ce = [min($ce[0] + 0.1, 4.6), min($ce[1] + 0.1, 4.7)];
                $notes[] = 'Thin walls: carbon equivalent raised 0.1 to avoid chill and carbides.';
            }
            $si = $thinWall < 6 ? [2.2, 2.8] : ($tier >= 2 ? [1.7, 2.2] : [1.9, 2.4]);
            $siMid = array_sum($si) / 2;
            $cMid = array_sum($ce) / 2 - $siMid / 3 - 0.05;
            $rows = [
                $this->row('C', $cMid - 0.1, $cMid + 0.1, $cMid, 'Set so that CE falls in the window.'),
                $this->row('Si', $si[0], $si[1], $siMid, $thinWall < 6 ? 'Higher Si fights chill in thin walls.' : 'Lower Si in heavy sections holds strength.'),
                $this->row('CE', $ce[0], $ce[1], array_sum($ce) / 2, 'C + Si/3 + P/3. Check by thermal analysis; spectrometer carbon reads low.'),
                $this->row('Mn', 0.5, 0.9, 0.7, 'At least 1.7 x S + 0.3.'),
                $this->row('S', 0.04, 0.12, 0.08, 'Enough S for good inoculation response; keep Mn balance.'),
                $this->row('P', 0.02, $tier >= 2 ? 0.06 : 0.10, $tier >= 2 ? 0.04 : 0.07, $tier >= 2 ? 'Keep low in heavy sections (steadite shrinkage).' : 'Helps fluidity in light work.'),
                $this->row('Cr', 0.0, 0.15, 0.05, 'Carbide former: keep as low as possible.'),
                $this->row('Cu', 0.0, $tier >= 2 ? 0.6 : 0.3, $tier >= 2 ? 0.4 : 0.1, $tier >= 2 ? 'Optional: holds pearlite in heavy sections.' : 'Optional.'),
            ];
            $inoc = $thinWall < 6 ? 'Ladle plus stream inoculation, about 0.15 to 0.30 % of 75 % FeSi.' : 'Ladle inoculation, about 0.10 to 0.20 % of 75 % FeSi.';
            $mg = null;
            $nodules = null;
        } else {
            $ce = [[4.5, 4.7], [4.4, 4.6], [4.3, 4.5], [4.2, 4.4]][$tier];
            $siF = $thinWall < 8 ? [2.5, 2.9] : ($tier >= 2 ? [2.0, 2.4] : [2.3, 2.7]);
            $siMid = array_sum($siF) / 2;
            $cMid = array_sum($ce) / 2 - $siMid / 3;
            $mgLow = $tier >= 2 ? 0.030 : 0.035;
            $mgHigh = $tier >= 2 ? 0.045 : 0.050;
            $fade = 0.001 * $window;
            $rows = [
                $this->row('C', $cMid - 0.1, $cMid + 0.1, $cMid, 'Set so that CE falls in the window.'),
                $this->row('Si (final)', $siF[0], $siF[1], $siMid, 'After Mg treatment and inoculation.'),
                $this->row('Si (base iron)', $siF[0] - 1.1, $siF[1] - 0.9, $siMid - 1.0, 'Treatment alloy and inoculant add about 0.9 to 1.1 % Si; adjust to your additions.'),
                $this->row('CE', $ce[0], $ce[1], array_sum($ce) / 2, 'C + Si/3 + P/3. Use thermal analysis; spectrometer carbon is unreliable.'),
                $this->row('Mn', 0.0, $tier >= 2 ? 0.20 : 0.35, $tier >= 2 ? 0.15 : 0.25, 'Low Mn avoids carbides in the last-freezing zones.'),
                $this->row('P', 0.0, $tier >= 2 ? 0.03 : 0.04, 0.025, 'Cannot be removed in the melt.'),
                $this->row('S (before treatment)', 0.0, 0.025, 0.012, 'Project limit: below 0.025 %. Lower S means less Mg used up.'),
                $this->row('Mg (residual at pour)', $mgLow, $mgHigh, ($mgLow + $mgHigh) / 2, 'Too high leads to carbides; too low to low nodularity.'),
                $this->row('Cr', 0.0, 0.05, 0.02, 'Carbide former.'),
            ];
            $mgTreat = ($mgLow + $mgHigh) / 2 + $fade;
            $mg = [
                'residual_target_pct' => ($mgLow + $mgHigh) / 2, 'window_min' => $window, 'fade_pct' => $fade,
                'treatment_target_pct' => $mgTreat,
                'text' => sprintf('Mg fades about 0.001 %% per minute in a pouring ladle. If the last mould is poured %.0f min after treatment, aim for about %.3f %% Mg after treatment so that about %.3f %% is left at the end. Free Mg = Mg - 0.75 x S, so with S at 0.012 %% the free Mg is about %.3f %%. Check each treatment on the spectrometer.', $window, $mgTreat, ($mgLow + $mgHigh) / 2, ($mgLow + $mgHigh) / 2 - 0.75 * 0.012),
            ];
            $inoc = $thinWall < 8 ? 'Strong inoculation: in-ladle plus late (stream or in-mould). Thin walls need a high nodule count to avoid carbides.' : ($tier >= 2 ? 'Moderate inoculation; avoid over-treatment in heavy sections. Control rare earths and consider trace Sb (plant trial) to suppress chunky graphite.' : 'Ladle plus stream inoculation, about 0.2 to 0.4 % of FeSi-based inoculant.');
            $nodules = [300, 200, 120, 80][$tier];
            if ($thinWall < 8) {
                $nodules = max($nodules, 300);
            }
        }

        $pour = $family === 'GREY'
            ? [[1370, 1420], [1340, 1390], [1310, 1360], [1290, 1340]][$tier]
            : [[1380, 1430], [1350, 1400], [1330, 1380], [1310, 1360]][$tier];
        if ($thinWall < $F['wall_advised_min_mm']) {
            $pour = [$pour[0] + 20, $pour[1] + 20];
            $notes[] = 'Pouring window raised 20 C because of thin walls.';
        }
        $pourText = sprintf('%d to %d C measured entering the sprue (ladle temperature is higher). Each 100 C of superheat adds about 1.5 %% liquid shrinkage to feed.', $pour[0], $pour[1]);

        // Grade fit
        $grade = $opt['grade'] ?? null;
        $conflicts = [];
        if ($grade) {
            $map = ['C' => 'c', 'Si' => 'si', 'Si (final)' => 'si', 'Mn' => 'mn', 'P' => 'p', 'S' => 's', 'Cr' => 'cr', 'Cu' => 'cu', 'Mg (residual at pour)' => 'mg'];
            foreach ($rows as &$row) {
                $key = $map[$row['element']] ?? null;
                if (! $key) {
                    continue;
                }
                $gmin = $grade[$key.'_min'] ?? null;
                $gmax = $grade[$key.'_max'] ?? null;
                $row['grade_min'] = $gmin;
                $row['grade_max'] = $gmax;
                if (($gmin !== null && $row['max'] < $gmin) || ($gmax !== null && $row['min'] > $gmax)) {
                    $conflicts[] = "{$row['element']}: the section-driven window ({$row['min']}-{$row['max']}) does not overlap the grade limits ({$gmin} to {$gmax}).";
                } elseif ($gmin !== null || $gmax !== null) {
                    $row['min'] = $gmin !== null ? max($row['min'], (float) $gmin) : $row['min'];
                    $row['max'] = $gmax !== null ? min($row['max'], (float) $gmax) : $row['max'];
                    $row['target'] = max($row['min'], min($row['max'], $row['target']));
                }
            }
            unset($row);
        }

        $candidates = [];
        foreach ($opt['grades'] ?? [] as $g) {
            if ($g['family'] !== $family) {
                continue;
            }
            if (! empty($opt['min_tensile']) && ($g['min_tensile_mpa'] ?? 0) < $opt['min_tensile']) {
                continue;
            }
            if (! empty($opt['min_elongation']) && ($g['min_elongation_pct'] ?? 0) < $opt['min_elongation']) {
                continue;
            }
            $candidates[] = $g;
        }
        usort($candidates, fn ($a, $b) => ($a['min_tensile_mpa'] ?? 0) <=> ($b['min_tensile_mpa'] ?? 0));

        return [
            'ce' => $ce, 'rows' => $rows, 'mg' => $mg, 'inoculation' => $inoc, 'nodule_target' => $nodules,
            'pour_temp' => $pour, 'pour_text' => $pourText, 'notes' => $notes, 'conflicts' => $conflicts,
            'grade_candidates' => array_slice($candidates, 0, 5),
            'section_tier' => ['Thin (M under 0.5 cm)', 'Light (0.5 to 1.5 cm)', 'Medium (1.5 to 2.5 cm)', 'Heavy (over 2.5 cm)'][$tier],
            'summary' => sprintf('Heaviest section M %.2f cm (%s), thinnest wall %.1f mm. Target CE %.1f to %.1f. %s', $mHeavy, ['thin', 'light', 'medium', 'heavy'][$tier], $thinWall, $ce[0], $ce[1], $family === 'DUCTILE' ? 'Base S below 0.025 %, residual Mg '.number_format($rows[7]['min'] ?? 0.035, 3).' to '.number_format($rows[7]['max'] ?? 0.05, 3).' %.' : 'Keep Cr low and inoculate.'),
        ];
    }

    private function row(string $el, float $min, float $max, float $target, string $note): array
    {
        $dp = in_array($el, ['Mg (residual at pour)'], true) ? 3 : 2;

        return ['element' => $el, 'min' => round(max(0, $min), $dp), 'max' => round($max, $dp), 'target' => round($target, $dp), 'note' => $note];
    }
}
