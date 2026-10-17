<?php

namespace App\Http\Controllers;

use App\Models\FlaskSize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** The flask sizes: families of interchangeable flasks that stations handle and patterns are made for. */
class FlaskSizeController extends Controller
{
    /** Adds a size; a quick add from a dropdown asks for JSON and gets back the option to add to it. */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->validated($request);
        $size = FlaskSize::create($data + ['code' => FlaskSize::freeCode($data['name']), 'status' => 'ACTIVE', 'sort_order' => FlaskSize::nextSortOrder()]);
        if ($request->wantsJson()) {
            return response()->json(['id' => $size->id, 'label' => $size->name], 201);
        }

        return back()->with('success', "Flask size {$size->name} added.");
    }

    public function edit(FlaskSize $flaskSize): View
    {
        return view('flasks.size-edit', ['size' => $flaskSize]);
    }

    public function update(Request $request, FlaskSize $flaskSize): RedirectResponse
    {
        $flaskSize->update($this->validated($request, $flaskSize) + $request->validate(['status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])]]));

        return redirect()->route('flasks.index')->with('success', "Flask size {$flaskSize->name} saved.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?FlaskSize $size = null): array
    {
        $mm = ['nullable', 'numeric', 'gt:0', 'max:99999'];
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'inside_length_mm' => $mm, 'inside_width_mm' => $mm, 'cope_depth_mm' => $mm, 'drag_depth_mm' => $mm, 'pin_centres_mm' => $mm,
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['name'] = FlaskSize::tidyName($data['name']);
        if (FlaskSize::nameTaken($data['name'], $size?->id)) {
            throw ValidationException::withMessages(['name' => "There is already a flask size called {$data['name']}."]);
        }

        return $data;
    }
}
