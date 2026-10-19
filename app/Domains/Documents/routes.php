<?php

use App\Domains\Documents\Http\Controllers\DocumentController;
use App\Domains\Documents\Http\Controllers\RevisionController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::prefix('documents')->name('documents.')->group(function () {
        Route::get('/', [DocumentController::class, 'index'])->name('index');
        Route::get('/create', [DocumentController::class, 'create'])->name('create');
        Route::post('/', [DocumentController::class, 'store'])->name('store');
        Route::get('/revisions/{revision}/file', [RevisionController::class, 'file'])->name('revisions.file');
        Route::put('/revisions/{revision}', [RevisionController::class, 'update'])->name('revisions.update');
        Route::post('/revisions/{revision}/submit', [RevisionController::class, 'submit'])->name('revisions.submit');
        Route::post('/approvals/{approval}/decide', [RevisionController::class, 'decide'])->name('revisions.decide');
        Route::get('/{document}', [DocumentController::class, 'show'])->name('show');
        Route::put('/{document}', [DocumentController::class, 'update'])->name('update');
        Route::post('/{document}/revisions', [RevisionController::class, 'store'])->name('revisions.store');
        Route::post('/{document}/obsolete', [DocumentController::class, 'obsolete'])->name('obsolete');
        Route::post('/{document}/reviewed', [DocumentController::class, 'reviewed'])->name('reviewed');
    });
});
