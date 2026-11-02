<?php

use App\Modules\Dons\Http\Controllers\Front\DonController;
use App\Modules\Dons\Http\Controllers\Front\RecuDonController;
use Illuminate\Support\Facades\Route;

// Vérification publique d'un reçu (ouverte par le QR code) : lien signé, sans connexion.
Route::get('/dons/recu/verifier/{id}', [RecuDonController::class, 'verifier'])
    ->middleware(['signed', 'throttle:30,1'])
    ->where('id', '[0-9a-fA-F]{24}')
    ->name('dons.recu.verifier');

Route::middleware('citizen')->group(function () {
    Route::get('/dons/{id}/recu', [RecuDonController::class, 'show'])->where('id', '[0-9a-fA-F]{24}')->name('dons.recu');
    Route::get('/dons',        [DonController::class, 'index'])->name('dons');
    Route::get('/dons/matching/{vetement}', [DonController::class, 'matching'])->name('dons.matching');
    Route::get('/dons/precontrole/{vetement}/{association}', [DonController::class, 'preControle'])->name('dons.precontrole');
    Route::post('/dons',       [DonController::class, 'store'])->name('dons.store');
    Route::put('/dons/{id}',   [DonController::class, 'update'])->name('dons.update');
    Route::delete('/dons/{id}',[DonController::class, 'destroy'])->name('dons.destroy');
});
