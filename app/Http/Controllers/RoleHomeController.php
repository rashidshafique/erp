<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\RoleHome;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The home of a role with no page of its own: counts for what its permissions cover. */
class RoleHomeController extends Controller
{
    public function __invoke(Request $request, RoleHome $home): View
    {
        $user = $request->user();

        return view('home', ['sections' => $user ? $home->sections($user) : [], 'user' => $user]);
    }
}
