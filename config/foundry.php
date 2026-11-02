<?php

return [
    // Gates can be switched off per plant while processes are being introduced.
    'require_contract_review' => env('FOUNDRY_REQUIRE_CONTRACT_REVIEW', true),
    'enforce_thermal_gate' => env('FOUNDRY_ENFORCE_THERMAL_GATE', true),

    'thermal' => [
        // Starting response of TEmin to inoculant, deg C per 0.1 % addition, until calibrated from history.
        'default_inoculant_gain_c_per_0p1pct' => 6.0,
        'max_inoculant_pct' => 0.40,
        // Uncalibrated liquidus relation: TL = a - b * CE (deg C). Replace by fitting your own cups.
        'tl_intercept_c' => 1669.0,
        'tl_slope_c_per_ce' => 124.5,
        'smoothing_points' => 5,
        // A passing test is only good for this long; iron fades and the pour window closes.
        'max_test_age_minutes' => 45,
    ],

    'planner' => [
        'furnace_fill_factor' => 0.90,         // share of rated capacity used per heat
        'default_yield' => 0.60,               // casting weight / poured weight when no cavity data
        'late_buffer_days' => 1,               // heat date must be this many days before required date
        'lead_time_days' => 7,                 // minimum days from today for contract review
        // Cupola default capability. Per-furnace rows in furnace_grade_capabilities override this.
        'cupola_families' => ['GREY'],
        'induction_families' => ['GREY', 'DUCTILE', 'CGI', 'WHITE', 'ALLOY'],
        // Order in which families are melted to limit furnace changeover.
        'family_sequence' => ['GREY', 'CGI', 'DUCTILE', 'ALLOY', 'WHITE'],
    ],

    // For now the menu shows only basic invoicing, bills and inventory. Set FOUNDRY_MENU_BASIC_ONLY=false for the full menu.
    // Hidden pages still open by their address and in search.
    'menu_basic_only' => (bool) env('FOUNDRY_MENU_BASIC_ONLY', true),
    'menu_basic_routes' => [
        'customers.index', 'products.index', 'invoices.index', 'receipts.index', 'receivables.index',
        'suppliers.index', 'bills.index', 'supplier-payments.index', 'payables.index',
        'materials.index', 'stock-balances.index', 'stock-locations.index', 'stock-reorder.index',
    ],

    // Off until the first administrator exists (php artisan foundry:make-admin), then set FOUNDRY_REQUIRE_LOGIN=true.
    'require_login' => (bool) env('FOUNDRY_REQUIRE_LOGIN', false),

    'attachments' => [
        // Private disk: files are only reachable through the app, never by direct URL.
        'disk' => env('FOUNDRY_ATTACHMENT_DISK', 'local'),
        'max_kb' => (int) env('FOUNDRY_ATTACHMENT_MAX_KB', 8192),
        'extensions' => ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'xlsx', 'xls', 'csv', 'docx', 'doc', 'txt', 'dxf', 'dwg', 'step', 'stp', 'stl'],
    ],
];
