<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Formats;
use App\Support\ProcessDepth;
use App\Support\ProcessRules;
use App\Support\SettingsNav;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class SettingsHomeController extends Controller
{
    public function __invoke(): View
    {
        $known = [];
        foreach (SettingsNav::sections() as $links) {
            foreach ($links as $label => [$route]) {
                $known[$label] = route($route);
            }
        }
        $known['Price lists'] = Route::has('price-lists.index') ? route('price-lists.index') : null;
        $known['Payroll rules'] = Route::has('hr.payroll.setup') ? route('hr.payroll.setup') : null;

        $values = [
            'General' => Formats::shortDate(Carbon::create(2026, 10, 18)).' · '.Formats::withCurrency(Formats::number(1234.5678, Formats::decimals())),
            'How you work' => ucfirst(strtolower(ProcessDepth::current())).' depth',
            'Users' => User::count().' '.(User::count() === 1 ? 'person' : 'people'),
        ];

        $all = ProcessRules::sections(true);
        foreach (ProcessRules::PAGES as $info) {
            $rules = collect(array_intersect_key($all, array_flip($info['sections'])))->flatten(1);
            $changed = $rules->filter(fn (array $r) => (string) $r['value'] !== (string) $r['default'])->count();
            $values[$info['title']] = $rules->count().' rules'.($changed ? ' · '.$changed.' changed' : '');
        }

        $placed = [];
        $areas = [];
        foreach (SettingsNav::AREAS as $name => $area) {
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
