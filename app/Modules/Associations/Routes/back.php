<?php

use App\Modules\Associations\Http\Controllers\Back\AssociationController;
use Illuminate\Support\Facades\Route;

Route::prefix('associations')->name('associations')->group(function () {
    Route::get('/',           [AssociationController::class, 'index'])->name('');
    Route::get('/creer',      [AssociationController::class, 'create'])->name('.create');
    Route::post('/',          [AssociationController::class, 'store'])->name('.store');
    Route::post('/besoins-ia', [AssociationController::class, 'besoinsDepuisTexte'])->name('.besoins-ia');
    Route::get('/{id}/edit',  [AssociationController::class, 'edit'])->name('.edit');
    Route::put('/{id}',       [AssociationController::class, 'update'])->name('.update');
    Route::delete('/{id}',    [AssociationController::class, 'destroy'])->name('.destroy');
});
