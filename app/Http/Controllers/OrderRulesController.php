<?php

namespace App\Http\Controllers;

use App\Support\OrderRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderRulesController extends Controller
{
    public function edit(): View
    {
        return view('settings.rules', [
            'title' => 'Order rules', 'intro' => 'How customer orders are confirmed and made live.',
            'rules' => OrderRules::RULES, 'values' => collect(OrderRules::RULES)->map(fn ($r, $k) => OrderRules::get($k))->all(),
            'action' => route('settings.orders'), 'backUrl' => Route::has('sales-orders.index') ? route('sales-orders.index') : route('settings.edit'), 'backLabel' => Route::has('sales-orders.index') ? 'Sales orders' : 'Settings',
            'note' => 'A small foundry can skip the review: Make live then creates and releases the production orders so the order feeds the next heat plan. A foundry working to a quality standard keeps the review required.',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (OrderRules::RULES as $key => [, , $options]) {
            $rules[$key] = ['sometimes', 'required', Rule::in(array_keys($options))];
        }
        foreach ($request->validate($rules) as $key => $value) {
            OrderRules::set($key, $value);
        }

        return back()->with('success', 'Order rules saved.');
    }
}
