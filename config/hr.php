<?php

/** HR defaults. Lists here seed the screens; everything is editable on screen afterwards. */
return [
    'employment_types' => ['PERMANENT' => 'Permanent', 'CONTRACT' => 'Fixed-term contract', 'DAILY_WAGE' => 'Daily wage', 'APPRENTICE' => 'Apprentice / trainee', 'CONSULTANT' => 'Consultant'],
    'statuses' => ['ONBOARDING' => 'Onboarding', 'PROBATION' => 'On probation', 'ACTIVE' => 'Active', 'NOTICE' => 'Serving notice', 'EXITED' => 'Left'],
    'genders' => ['M' => 'Male', 'F' => 'Female', 'X' => 'Other'],
    'marital' => ['SINGLE' => 'Single', 'MARRIED' => 'Married', 'OTHER' => 'Other'],
    'location_kinds' => ['PLANT' => 'Plant / foundry', 'OFFICE' => 'Office', 'BRANCH' => 'Branch', 'WAREHOUSE' => 'Warehouse / yard'],
    'document_kinds' => ['CNIC' => 'CNIC / ID card', 'PASSPORT' => 'Passport', 'CONTRACT' => 'Contract / appointment letter', 'QUALIFICATION' => 'Degree / certificate', 'LICENCE' => 'Licence (driving, crane, welding)', 'MEDICAL' => 'Medical fitness', 'POLICE' => 'Police / character certificate', 'OTHER' => 'Other'],
    'exit_types' => ['RESIGNATION' => 'Resignation', 'TERMINATION' => 'Termination', 'RETIREMENT' => 'Retirement', 'END_OF_CONTRACT' => 'End of contract', 'ABSCONDED' => 'Absconded', 'DECEASED' => 'Deceased'],
    'policy_categories' => ['CONDUCT' => 'Conduct and discipline', 'SAFETY' => 'Safety and PPE', 'ATTENDANCE' => 'Attendance and leave', 'PAY' => 'Pay and benefits', 'GENERAL' => 'General'],
    'probation_months' => 3,
    'alert_days' => 30,

    'checklists' => [
        'ONBOARDING' => [
            ['Collect CNIC copy, photographs and educational documents', 'HR', 0],
            ['Issue and sign appointment letter', 'HR', 0],
            ['Safety induction and PPE issue', 'SAFETY', 1],
            ['Issue ID card and gate access', 'HR', 1],
            ['Introduce to supervisor, team and station', 'MANAGER', 1],
            ['Record bank account and NTN for payroll', 'PAYROLL', 3],
            ['Register for EOBI and social security', 'HR', 7],
            ['Medical fitness check', 'SAFETY', 7],
            ['Read and acknowledge the HR policies', 'EMPLOYEE', 7],
        ],
        'EXIT' => [
            ['Accept resignation and confirm last working day', 'HR', 0],
            ['Hand over work and open jobs', 'MANAGER', 0],
            ['Return tools, PPE, ID card and keys', 'STORES', 0],
            ['Clear advances and loans', 'PAYROLL', 0],
            ['Remove system and gate access', 'IT', 0],
            ['Exit interview', 'HR', 0],
            ['Prepare final settlement', 'PAYROLL', 0],
        ],
    ],

    // Attendance rules. Everything here is a starting value; shifts carry their own grace and break.
    'weekly_off_options' => ['0' => 'Sunday', '5' => 'Friday', '6,0' => 'Saturday and Sunday', '' => 'None (the roster decides)'],
    'attendance' => [
        'half_day_ratio' => 0.5,          // worked less than this share of the paid shift counts as a half day
        'overtime_min_minutes' => 30,     // time past the end of the shift below this is not overtime
        'overtime_auto_approve' => false,
        'day_status' => ['PRESENT' => 'Present', 'HALF_DAY' => 'Half day', 'ABSENT' => 'Absent', 'LEAVE' => 'On leave', 'OFF' => 'Weekly off', 'HOLIDAY' => 'Holiday'],
        'flags' => ['LATE' => 'Late', 'EARLY_OUT' => 'Left early', 'MISSING_IN' => 'No check-in', 'MISSING_OUT' => 'No check-out', 'ABSENT' => 'Absent', 'OVERTIME' => 'Overtime', 'WORKED_OFF' => 'Worked on a day off'],
    ],
    'default_shifts' => [
        ['Day shift', '08:00', '17:00', 60, 10, false],
        ['Evening shift', '16:00', '00:00', 30, 10, false],
        ['Night shift', '00:00', '08:00', 30, 10, true],
    ],

    // Placeholders. Check them against the labour law that applies to your province and your contracts.
    'leave_types' => [
        // code, name, paid, days a year, accrual, carry-forward cap, half day, counts days off, only after probation, gender
        ['AL', 'Annual leave', true, 14, 'MONTHLY', 14, true, false, true, null],
        ['CL', 'Casual leave', true, 10, 'UPFRONT', 0, true, false, false, null],
        ['SL', 'Sick leave', true, 8, 'UPFRONT', 0, true, false, false, null],
        ['ML', 'Maternity leave', true, 90, 'NONE', 0, false, true, true, 'F'],
        ['UL', 'Unpaid leave', false, 0, 'NONE', 0, true, false, false, null],
    ],
    'fixed_holidays' => [['02-05', 'Kashmir Solidarity Day'], ['03-23', 'Pakistan Day'], ['05-01', 'Labour Day'], ['08-14', 'Independence Day'], ['12-25', 'Quaid-e-Azam Day']],
];
