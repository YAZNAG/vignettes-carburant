<?php

namespace App\Providers;

use App\Models\Parametre;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Durée d'inactivité avant déconnexion paramétrable par l'administrateur.
        // Lue avant le démarrage de la session ; silencieux tant que la table
        // n'existe pas (migrations, première installation).
        if (! $this->app->runningInConsole()) {
            try {
                config(['session.lifetime' => Parametre::dureeInactiviteMinutes()]);
            } catch (\Throwable) {
                // base indisponible ou non migrée : on garde la valeur du .env
            }
        }

        Schema::defaultStringLength(191);
    }
}
