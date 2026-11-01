<?php

namespace App\Modules\Associations\Http\Middleware;

use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\RoleNavigationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un compte association validé doit créer sa fiche avant d'utiliser le reste du back-office.
 */
class EnsureAssociationHasCreatedFiche
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! RoleNavigationService::associationDoitCreerSaFiche($user)) {
            return $next($request);
        }

        $route = $request->route()?->getName();

        if (in_array($route, [
            'back.associations.create',
            'back.associations.store',
            'back.associations.besoins-ia',
        ], true)) {
            return $next($request);
        }

        return redirect()
            ->route('back.associations.create')
            ->with('info', 'Créez d\'abord la fiche de votre association pour continuer.');
    }
}
