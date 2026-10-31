<?php

use App\Domains\Development\Http\Controllers\PatternDevelopmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::prefix('developments')->name('developments.')->controller(PatternDevelopmentController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{development}', 'show')->name('show');
        Route::post('/{development}/advance', 'advance')->name('advance');
        Route::post('/{development}/back', 'back')->name('back');
        Route::put('/{development}/cost', 'cost')->name('cost');
        Route::post('/{development}/notes', 'note')->name('notes');
        Route::post('/{development}/cancel', 'cancel')->name('cancel');
        Route::post('/{development}/tools', 'toolStore')->name('tools.store');
        Route::put('/{development}/tools/{tool}', 'toolUpdate')->name('tools.update');
        Route::post('/{development}/tools/{tool}/register', 'toolRegister')->name('tools.register');
        Route::post('/{development}/tools/{tool}/link', 'toolLink')->name('tools.link');
        Route::delete('/{development}/tools/{tool}', 'toolDestroy')->name('tools.destroy');
    });
});
