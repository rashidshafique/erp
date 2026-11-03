<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Formats;
use App\Support\ProcessDepth;
use App\Support\SettingsNav;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class SettingsHomeController extends Controller
{
    /** @var array<string, array{perm: string, pages: list<string>}> area => the settings pages it holds, by menu label */
    public const AREAS = [
        'Company' => ['perm' => 'settings.manage', 'pages' => ['General', 'Codes & marking', 'Documents']],
        'People & approvals' => ['perm' => 'users.manage', 'pages' => ['Users', 'Roles & permissions', 'Approvals']],
        'Sales' => ['perm' => 'settings.manage', 'pages' => ['Quotation rates', 'Price lists', 'Requirement profiles']],
        'Production' => ['perm' => 'settings.manage', 'pages' => ['Foundry size', 'How you work', 'Melting days', 'Process rules', 'Our process']],
        'Quality' => ['perm' => 'settings.manage', 'pages' => ['Quality rules', 'Calibration & incoming']],
        'Accounts' => ['perm' => 'finance / ledger / tax.manage', 'pages' => ['Invoicing', 'Tax & year end', 'Ledger accounts', 'Posting rules', 'Sales documents']],
        'HR' => ['perm' => 'hr.manage · hr.payroll', 'pages' => ['Payroll rules']],
        'System' => ['perm' => 'settings.manage', 'pages' => ['Custom fields', 'Modules', 'Audit trail', 'Import data']],
    ];

    public function __invoke(): View
    {
        $known = [];
        foreach (SettingsNav::sections() as $links) {
            foreach ($links as $label => [$route]) {
                $known[$label] = route($route);
            }
        }
        $known['Price lists'] = Route::has('price-lists.index') ? route('price-lists.index') : null;
        $known['Quality rules'] = route('settings.rules', ['advanced' => 1]).'#quality';
        $known['Calibration & incoming'] = route('settings.rules', ['advanced' => 1]).'#calibration';
        $known['Payroll rules'] = Route::has('hr.payroll.setup') ? route('hr.payroll.setup') : null;

        $values = [
            'General' => Formats::shortDate(Carbon::create(2026, 10, 18)).' · '.Formats::withCurrency(Formats::number(1234.5678, Formats::decimals())),
            'How you work' => ucfirst(strtolower(ProcessDepth::current())).' depth',
            'Users' => User::count().' '.(User::count() === 1 ? 'person' : 'people'),
        ];

        $placed = [];
        $areas = [];
        foreach (self::AREAS as $name => $area) {
            $pages = [];
            foreach ($area['pages'] as $label) {
                if (! empty($known[$label])) {
                    $pages[] = ['name' => $label, 'href' => $known[$label], 'val' => $values[$label] ?? ''];
                    $placed[$label] = true;
                }
            }
            $areas[$name] = ['perm' => $area['perm'], 'pages' => $pages];
        }
        foreach (SettingsNav::sections() as $links) {   // pages a module added that the design does not name go under System
            foreach ($links as $label => [$route]) {
                if (! isset($placed[$label])) {
                    $areas['System']['pages'][] = ['name' => $label, 'href' => route($route), 'val' => ''];
                }
            }
        }

        return view('settings.home', [
            'areas' => array_filter($areas, fn ($a) => $a['pages'] !== []),
            'summary' => 'Process depth '.ucfirst(strtolower(ProcessDepth::current())),
            'loginRequired' => (bool) config('foundry.require_login'),
            'developer' => [
                'Sign-in required' => [config('foundry.require_login') ? 'On' : 'Off', ! config('foundry.require_login')],
                'Contract review required' => [config('foundry.require_contract_review') ? 'On' : 'Off', false],
                'Thermal gate enforced' => [config('foundry.enforce_thermal_gate') ? 'On' : 'Off', false],
                'Basic menu only' => [config('foundry.menu_basic_only') ? 'On' : 'Off', false],
                'Attachment limit' => [round(config('foundry.attachments.max_kb') / 1024).' MB', false],
                'Attachment storage' => [ucfirst((string) config('foundry.attachments.disk')), false],
                'Planner' => ['Fill '.number_format((float) config('foundry.planner.furnace_fill_factor'), 2).' · yield '.round(config('foundry.planner.default_yield') * 100).'% · lead '.config('foundry.planner.lead_time_days').' d', false],
                'Thermal limits' => ['Max test age '.config('foundry.thermal.max_test_age_minutes').' min', false],
            ],
        ]);
    }
}
