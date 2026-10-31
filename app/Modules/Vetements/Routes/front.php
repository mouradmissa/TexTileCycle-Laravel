<?php

use App\Modules\Vetements\Http\Controllers\Front\VetementController;
use Illuminate\Support\Facades\Route;

Route::middleware('citizen')->group(function () {
    Route::get('/mes-vetements', [VetementController::class, 'index'])->name('vetements');
    Route::get('/mon-historique', [VetementController::class, 'historique'])->name('historique');
    Route::post('/mes-vetements', [VetementController::class, 'store'])->name('vetements.store');
    Route::post('/mes-vetements/analyse-photo', [VetementController::class, 'analyser'])
        ->middleware('throttle:20,1')
        ->name('vetements.analyser');
    Route::post('/mes-vetements/chat', [VetementController::class, 'discuter'])
        ->middleware('throttle:20,1')
        ->name('vetements.chat');
    Route::post('/mes-vetements/style', [VetementController::class, 'proposerStyle'])
        ->middleware('throttle:6,1')
        ->name('vetements.style');
    Route::post('/mes-vetements/{vetement}/style', [VetementController::class, 'proposerStylePiece'])
        ->middleware('throttle:6,1')
        ->name('vetements.style.piece');
    Route::post('/mes-vetements/restyle', [VetementController::class, 'restyler'])
        ->middleware('throttle:6,1')
        ->name('vetements.restyle');
    Route::post('/mes-vetements/restyle/guide', [VetementController::class, 'enregistrerGuide'])
        ->middleware('throttle:6,1')
        ->name('vetements.restyle.guide');
    Route::post('/mes-vetements/{vetement}/restyle', [VetementController::class, 'restylerPiece'])
        ->middleware('throttle:6,1')
        ->name('vetements.restyle.piece');
    Route::post('/mes-vetements/{vetement}/analyse-photo', [VetementController::class, 'analyserPiece'])
        ->middleware('throttle:20,1')
        ->name('vetements.analyser.piece');
    Route::patch('/mes-vetements/{vetement}/action', [VetementController::class, 'updateAction'])->name('vetements.action');
});
