<?php

/**
 * Starting figures for the quotation cost build-up, in PKR. They are PLACEHOLDERS: replace them with your own
 * costs on Settings → Quotation rates. Values saved there override these.
 */
return [
    'currency' => 'PKR',
    'validity_days' => 30,
    'default_family' => 'GREY',
    'rates' => [
        'melt_grey_per_kg' => 160.0,      // metal in the ladle: charge, energy, alloys, losses, net of returns recovered
        'melt_ductile_per_kg' => 210.0,   // as above plus Mg treatment and inoculation
        'moulding_per_kg' => 25.0,        // per kg poured
        'core_each' => 120.0,             // per core made and set
        'fettling_per_kg' => 18.0,        // per kg of casting
        'heat_treatment_per_kg' => 30.0,  // per kg of casting, when heat treated
        'testing_each' => 15.0,           // chemistry and mechanical test share, per casting
        'ndt_each' => 60.0,               // extra per casting when NDT is required
        'packing_each' => 20.0,           // per casting
        'overhead_pct' => 12.0,           // on conversion cost (everything except metal, machining and other)
        'margin_pct' => 18.0,             // margin on price
        'tooling_markup_pct' => 20.0,     // added to tooling cost
        'min_margin_pct' => 10.0,         // below this an approval is asked for (when that approval policy is on)
        'default_yield_pct' => 60.0,      // used when no analysis gives a yield
        'scrap_default_pct' => 6.0,       // when there is no analysis to judge difficulty
    ],
    // Expected scrap by manufacturability score from the casting analysis: [minimum score => scrap %].
    'scrap_by_score' => [85 => 3.0, 70 => 5.0, 55 => 8.0, 40 => 12.0, 0 => 18.0],
];
