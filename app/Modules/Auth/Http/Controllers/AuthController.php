<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Http\Requests\ProfilCitoyenRequest;
use App\Modules\Auth\Http\Requests\RegisterRequest;
use App\Modules\Auth\Services\RoleNavigationService;
use App\Modules\Auth\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(
        private UserService $users
    ) {
    }

    public function login()
    {
        return view('front.auth.login');
    }

    public function loginStore(LoginRequest $request)
    {
        $user = $this->users->findAuthenticatable(
            $request->input('email'),
            $request->input('password')
        );

        if (! $user) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Identifiants incorrects.']);
        }

        if (! $user->isActive()) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => $user->inactiveAccountMessage()]);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(RoleNavigationService::redirectAfterLogin($user));
    }

    public function register()
    {
        return view('front.auth.register');
    }

    public function registerStore(RegisterRequest $request)
    {
        $user = $this->users->create($request->validated());

        if (! $user->isActive()) {
            return redirect()
                ->route('front.login')
                ->with('success', 'Votre compte '.$user->roleLabel().' a été créé. Il sera activé après validation administrative.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect(RoleNavigationService::redirectAfterLogin($user))
            ->with('success', 'Bienvenue sur TexTileCycle, '.$user->name.' !');
    }

    public function profil()
    {
        $user = auth()->user();
        abort_unless($user && $user->isCitoyen(), 403, 'Cette page est réservée aux comptes client.');

        return view('front.profil', ['user' => $user]);
    }

    public function profilUpdate(ProfilCitoyenRequest $request)
    {
        $this->users->mettreAJourProfil($request->user(), $request->validated());

        return redirect()
            ->route('front.profil')
            ->with('success', 'Vos informations ont été enregistrées.');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('front.home');
    }
}
