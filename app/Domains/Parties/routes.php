<?php

use App\Domains\Parties\Http\Controllers\PartyController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::prefix('partners')->name('parties.')->controller(PartyController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{party}', 'show')->name('show');
        Route::get('/{party}/edit', 'edit')->name('edit');
        Route::put('/{party}', 'update')->name('update');
        Route::post('/{party}/roles/{role}', 'addRole')->whereIn('role', ['customer', 'supplier'])->name('roles.add');
    });
});
