<?php

namespace App\Modules\Ateliers\Providers;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Service;
use App\Modules\Ateliers\Policies\AtelierPolicy;
use App\Modules\Ateliers\Policies\ServicePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AteliersModuleServiceProvider extends ServiceProvider
{
    /**
     * Limiteur des validations en temps réel (Precognition) des formulaires du module.
     */
    public const LIMITEUR_PRECOGNITION = 'ateliers-precognition';

    public const PRECOGNITIONS_PAR_MINUTE = 60;

    /**
     * Limiteur du diagnostic par photo (chaque analyse occupe le processeur du microservice).
     */
    public const LIMITEUR_DIAGNOSTIC = 'ateliers-diagnostic';

    /** Questions à l'assistant de réparation : chacune occupe le service Python et peut appeler Gemini. */
    public const LIMITEUR_ASSISTANT = 'ateliers-assistant';

    /**
     * Limiteur des avis (publication, modification, suppression) : contre les envois en rafale.
     */
    public const LIMITEUR_AVIS = 'ateliers-avis';

    public const AVIS_PAR_MINUTE = 10;

    /**
     * Limiteur des pages publiques de passeport (QR) : une consultation normale reste loin de la limite,
     * un balayage de codes au hasard est freiné.
     */
    public const LIMITEUR_PASSEPORT = 'ateliers-passeport';

    public const PASSEPORTS_PAR_MINUTE = 60;

    /** Images de QR (plusieurs par page dans « Mes passeports ») : limite plus large que les pages. */
    public const LIMITEUR_PASSEPORT_QR = 'ateliers-passeport-qr';

    public const QR_PAR_MINUTE = 300;

    public function register(): void
    {
        $this->mergeConfigFrom(app_path('Modules/Ateliers/Config/diagnostic.php'), 'ateliers_diagnostic');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(app_path('Modules/Ateliers/Database/Migrations'));

        Gate::policy(Atelier::class, AtelierPolicy::class);
        Gate::policy(Service::class, ServicePolicy::class);

        // Seules les validations en temps réel sont limitées (par compte) : l'envoi réel du formulaire ne l'est jamais.
        RateLimiter::for(self::LIMITEUR_PRECOGNITION, function (Request $request) {
            return $request->isAttemptingPrecognition()
                ? Limit::perMinute(self::PRECOGNITIONS_PAR_MINUTE)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()))
                : Limit::none();
        });

        RateLimiter::for(self::LIMITEUR_DIAGNOSTIC, function (Request $request) {
            return Limit::perMinute(max(1, (int) config('ateliers_diagnostic.par_minute')))
                ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        RateLimiter::for(self::LIMITEUR_ASSISTANT, function (Request $request) {
            return Limit::perMinute(max(1, (int) config('ateliers_diagnostic.assistant_par_minute')))
                ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        RateLimiter::for(self::LIMITEUR_PASSEPORT, function (Request $request) {
            return Limit::perMinute(self::PASSEPORTS_PAR_MINUTE)->by($request->ip());
        });

        RateLimiter::for(self::LIMITEUR_PASSEPORT_QR, function (Request $request) {
            return Limit::perMinute(self::QR_PAR_MINUTE)->by($request->ip());
        });

        RateLimiter::for(self::LIMITEUR_AVIS, function (Request $request) {
            return Limit::perMinute(self::AVIS_PAR_MINUTE)
                ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });
    }
}
