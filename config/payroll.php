<?php

/**
 * Payroll defaults. EVERY number here is a PLACEHOLDER to be checked against the current Finance Act / SRO,
 * EOBI and provincial social-security notifications, and your own contracts. HR edits the live values on screen
 * (Payroll → Rules); what is saved there replaces these.
 */
return [
    'pay_modes' => ['BANK' => 'Bank transfer', 'CASH' => 'Cash', 'CHEQUE' => 'Cheque'],
    'benefit_kinds' => ['CASH' => 'Paid in cash with salary', 'IN_KIND' => 'Provided in kind (not paid out)'],

    'rules' => [
        // How a day's pay is worked out
        'days_basis' => 'CALENDAR',          // CALENDAR = days in the month; or a fixed number such as 26 or 30
        'missing_attendance' => 'PRESENT',   // a working day nobody closed: PRESENT or ABSENT
        'late_marks_per_day' => 0,           // every N late arrivals cut one day's pay; 0 = off
        'half_day_other_half' => 'UNPAID',   // a half day worked for short hours: the other half is UNPAID or PAID
        // Overtime
        'overtime_multiplier' => 2.0,
        'overtime_base' => 'BASIC',          // BASIC or GROSS (full month, before absences)
        'hours_per_day' => 8,
        // Rounding of every money line: 1 = whole rupee, 0.01 = paisa
        'round_to' => 1,
        // Advances and loans
        'max_recovery_percent' => 50,        // never recover more than this share of what is left after tax and statutory deductions
        // Income tax on salary (annual slabs, placeholders; confirm for the tax year in force)
        'tax_enabled' => true,
        'tax_year_note' => 'PLACEHOLDER slabs — confirm against the current Finance Act',
        'tax_slabs' => [
            ['from' => 0, 'rate' => 0, 'fixed' => 0],
            ['from' => 600000, 'rate' => 1, 'fixed' => 0],
            ['from' => 1200000, 'rate' => 11, 'fixed' => 6000],
            ['from' => 2200000, 'rate' => 23, 'fixed' => 116000],
            ['from' => 3200000, 'rate' => 30, 'fixed' => 346000],
            ['from' => 4100000, 'rate' => 35, 'fixed' => 616000],
        ],
        'tax_surcharge_over' => 10000000,    // annual income above this pays the surcharge
        'tax_surcharge_percent' => 9,
        // EOBI (old-age benefits): a share of a fixed wage base, not of the salary
        'eobi_enabled' => true,
        'eobi_wage_base' => 37000,
        'eobi_employee_percent' => 1,
        'eobi_employer_percent' => 5,
        // Provincial social security (employer share of wages up to a ceiling)
        'ss_enabled' => true,
        'ss_employer_percent' => 6,
        'ss_wage_ceiling' => 40000,
        // Final settlement
        'gratuity_enabled' => true,
        'gratuity_days_per_year' => 30,      // days of last basic pay per completed year
        'gratuity_min_years' => 1,
        'gratuity_types' => ['PERMANENT'],
        'encash_leave_codes' => ['AL'],      // leave types paid out as cash when someone leaves
        'notice_days' => 30,
    ],

    'default_components' => [
        // code, name, kind, calc, default value, taxable, prorate, is_basic
        ['BASIC', 'Basic salary', 'EARNING', 'FIXED', 0, true, true, true],
        ['HOUSE', 'House rent allowance', 'EARNING', 'PERCENT_BASIC', 40, true, true, false],
        ['CONV', 'Conveyance allowance', 'EARNING', 'FIXED', 0, true, true, false],
        ['MEDICAL', 'Medical allowance', 'EARNING', 'FIXED', 0, false, true, false],
    ],
];
