<?php

/**
 * Casting analysis constants. These are starting values for sand-cast iron: tune them to your own plant
 * practice and pour history. Moduli are in cm, thicknesses in mm unless a key says otherwise.
 */
return [
    'max_upload_kb' => (int) env('FOUNDRY_CAD_MAX_KB', 61440),
    'max_triangles' => 700000,
    'memory_limit' => '1024M',
    'time_limit_seconds' => 240,

    // Optional STEP/STP reader. STL needs nothing. {in} and {out} are replaced with temporary file paths.
    // The helper in tools/step_to_stl.py needs: pip install cadquery
    'step_converter' => env('FOUNDRY_STEP_CONVERTER', 'python3 '.base_path('tools/step_to_stl.py').' {in} {out}'),
    'step_timeout_seconds' => 180,

    'units_to_mm' => ['mm' => 1.0, 'cm' => 10.0, 'm' => 1000.0, 'in' => 25.4],

    // Voxel grid size by quality setting (cells in the box around the part).
    'resolution' => ['DRAFT' => 150000, 'STANDARD' => 500000, 'FINE' => 1200000],
    'max_cells' => 1500000,
    // Aim for a voxel no bigger than this share of the average wall (2 x volume / area), when the cell budget allows.
    'voxel_to_wall' => 0.25,

    'modulus' => [
        // Window half-size around each point, in inscribed radii. The window gives the local volume / cooling area.
        'window_radii' => 2.0,
        'significant_peak_share' => 0.30,   // ignore bumps below this share of the largest modulus
        'min_prominence_voxels' => 0.75,    // peak must stand this many voxels (in modulus) above its neck
        // Neck modulus / hotspot modulus. The modulus is measured to about +-8 %, so a neck must be clearly lower
        // than the hotspot (here 15 %) before the hotspot is called cut off. Design rule of thumb stays neck >= 1.1 x casting.
        'neck_ok_ratio' => 0.85,
        'severe_ratio' => 0.70,
    ],

    // Per iron family. B is Chvorinov's constant in min/cm2, a relative guide until calibrated on your own pours.
    'families' => [
        'GREY' => [
            'label' => 'Grey iron',
            'density' => 7.10, 'chvorinov_b' => 1.6,
            'feed_demand_pct' => 2.0, 'shrink_allow_pct' => 1.0,
            'wall_hard_min_mm' => 3.0, 'wall_advised_min_mm' => 4.0,
            'gating_ratio' => [1, 0.8, 0.6], 'choke_at' => 'ingate', 'gating_note' => 'pressurised', 'filter' => false,
            'pour_k' => 2.0, 'feeder_modulus_margin' => 1.0,
        ],
        'DUCTILE' => [
            'label' => 'Ductile (SG) iron',
            'density' => 7.10, 'chvorinov_b' => 1.8,
            'feed_demand_pct' => 3.5, 'shrink_allow_pct' => 1.0,
            'wall_hard_min_mm' => 4.0, 'wall_advised_min_mm' => 6.0,
            'gating_ratio' => [1, 2, 1.5], 'choke_at' => 'sprue', 'gating_note' => 'unpressurised, low turbulence', 'filter' => true,
            'pour_k' => 2.0, 'feeder_modulus_margin' => 1.1,
        ],
    ],

    // Feeder efficiency (share of feeder volume available to feed) and modulus extension of the sleeve.
    'feeders' => [
        'CONVENTIONAL' => ['label' => 'Sand riser', 'efficiency' => 0.14, 'mef' => 1.0],
        'INSULATED' => ['label' => 'Insulating sleeve', 'efficiency' => 0.30, 'mef' => 1.3],
        'EXOTHERMIC' => ['label' => 'Exothermic sleeve', 'efficiency' => 0.35, 'mef' => 1.4],
        'height_to_diameter' => 1.5,
        'modulus_margin' => 1.2,   // steel-style default; each iron family below overrides it (graphite expansion helps feeding)
        'neck_margin' => 1.1,      // neck modulus / casting hotspot modulus
    ],

    'moulding' => [
        'min_draft_deg' => 1.0,
        'parting_candidates' => 9,
        'undercut_share_limit' => 0.25,
    ],

    'weights' => ['feeding' => 35, 'walls' => 20, 'uniformity' => 15, 'moulding' => 20, 'yield' => 10],
];
