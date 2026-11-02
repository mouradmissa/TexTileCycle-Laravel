<?php

namespace App\Providers;

use App\Mail\Transport\RelaisHttpsTransport;
use App\Modules\Core\Support\AtlasIpSynchronizer;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Mail::extend('relais', function (array $config = []) {
            return new RelaisHttpsTransport(
                (string) ($config['url'] ?? ''),
                (string) ($config['secret'] ?? '')
            );
        });

        if (! AtlasIpSynchronizer::shouldRunForCurrentProcess()) {
            return;
        }

        try {
            $ip = app(AtlasIpSynchronizer::class)->sync();
            fwrite(STDERR, "MongoDB Atlas : IP {$ip} autorisée pour ce lancement.\n");
        } catch (Throwable $e) {
            fwrite(STDERR, "MongoDB Atlas : {$e->getMessage()}\n");
        }
    }
}
