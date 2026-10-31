<?php

use App\Modules\Auth\Http\Controllers\AuthController;
use App\Modules\Auth\Services\RoleNavigationService;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [AuthController::class, 'login'])->name('login');
    Route::post('/connexion', [AuthController::class, 'loginStore'])->name('login.store');
    Route::get('/inscription', [AuthController::class, 'register'])->name('register');
    Route::post('/inscription', [AuthController::class, 'registerStore'])->name('register.store');
});

Route::post('/deconnexion', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware('citizen')->group(function () {
    Route::get('/mon-profil', [AuthController::class, 'profil'])->name('profil');
    Route::put('/mon-profil', [AuthController::class, 'profilUpdate'])->name('profil.update');
});
