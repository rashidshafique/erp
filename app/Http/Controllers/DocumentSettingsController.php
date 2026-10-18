<?php

namespace App\Http\Controllers;

use App\Support\AttachableEntities;
use App\Support\DocumentVersioning;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DocumentSettingsController extends Controller
{
    public function edit(): View
    {
        return view('settings.documents', [
            'enabled' => DocumentVersioning::enabled(), 'related' => DocumentVersioning::showRelated(), 'selected' => DocumentVersioning::categories(), 'categories' => AttachableEntities::CATEGORIES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'categories' => ['array'], 'categories.*' => [Rule::in(array_keys(AttachableEntities::CATEGORIES))],
        ]);
        DocumentVersioning::save($request->boolean('versioning'), $d['categories'] ?? [], $request->boolean('related'));

        return back()->with('success', 'Document settings saved. Files already uploaded keep their history either way.');
    }
}
