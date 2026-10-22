<?php

namespace App\Domains\Licensing\Http\Controllers;

use App\Domains\Licensing\FoundrySize;
use App\Http\Controllers\Controller;
use App\Support\ProcessDepth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FoundrySizeController extends Controller
{
    public function edit(FoundrySize $size): View
    {
        return view('licensing::size', ['sizes' => FoundrySize::SIZES, 'current' => $size->current(), 'depth' => ProcessDepth::current()]);
    }

    public function update(Request $request, FoundrySize $size): RedirectResponse
    {
        $chosen = $request->validate(['size' => ['required', Rule::in(array_keys(FoundrySize::SIZES))]])['size'];
        $size->apply($chosen);

        return back()->with('success', FoundrySize::SIZES[$chosen]['label'].' foundry size applied. The menu and process rules have changed.');
    }
}
