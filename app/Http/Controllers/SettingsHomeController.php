<?php

namespace App\Http\Controllers;

use App\Support\SettingsNav;
use Illuminate\View\View;

class SettingsHomeController extends Controller
{
    /** One line on what each settings page is for, by its menu label. */
    public const PURPOSE = [
        'General' => 'Look of the screens, dates, numbers, currency, font and logo.',
        'Codes & marking' => 'Record codes and what is marked on castings.',
        'Documents' => 'Layouts of the documents the app prints.',
        'Custom fields' => 'Extra fields on records, shown on forms, lists and prints.',
        'How you work' => 'A few questions that set the common process rules.',
        'Process rules' => 'Every rule that makes a step optional or required.',
        'Our process' => 'Steps after the pour, in your order.',
        'Melting days' => 'Which days the foundry melts.',
        'Users' => 'Who can sign in.',
        'Roles & permissions' => 'What each role may see and change.',
        'Approvals' => 'Which documents need an approval and from whom.',
        'Audit trail' => 'Every change, with the old and new value.',
        'Import data' => 'Bring in records from spreadsheets.',
    ];

    public function __invoke(): View
    {
        $sections = SettingsNav::sections();
        unset($sections['Overview']);

        return view('settings.home', [
            'sections' => $sections,
            'purpose' => self::PURPOSE,
            'loginRequired' => (bool) config('foundry.require_login'),
            'developer' => [
                'Sign-in required' => config('foundry.require_login') ? 'On' : 'Off',
                'Contract review required' => config('foundry.require_contract_review') ? 'On' : 'Off',
                'Thermal gate enforced' => config('foundry.enforce_thermal_gate') ? 'On' : 'Off',
                'Basic menu only' => config('foundry.menu_basic_only') ? 'On' : 'Off',
                'Attachment limit' => round(config('foundry.attachments.max_kb') / 1024).' MB',
                'Attachment storage' => ucfirst((string) config('foundry.attachments.disk')),
                'Planner' => 'Fill '.number_format((float) config('foundry.planner.furnace_fill_factor'), 2).' · yield '.round(config('foundry.planner.default_yield') * 100).'% · lead '.config('foundry.planner.lead_time_days').' d',
                'Thermal limits' => 'Max test age '.config('foundry.thermal.max_test_age_minutes').' min',
            ],
        ]);
    }
}
