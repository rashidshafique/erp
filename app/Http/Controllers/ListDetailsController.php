<?php

namespace App\Http\Controllers;

use App\Support\ListLayout;
use Illuminate\Http\RedirectResponse;

/** The Details toggle on the plain (non-Livewire) lists: flips the shared choice and goes back to the list. */
class ListDetailsController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        ListLayout::setDetails(! ListLayout::details());

        return back();
    }
}
