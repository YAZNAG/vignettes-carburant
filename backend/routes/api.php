<?php

use App\Http\Controllers\Audit\AuditLogController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Referentiels\BeneficiaireController;
use App\Http\Controllers\Referentiels\CoupureController;
use App\Http\Controllers\Referentiels\ExerciceController;
use App\Http\Controllers\Referentiels\FournisseurController;
use App\Http\Controllers\Referentiels\ImportController;
use App\Http\Controllers\Referentiels\MotifSortieController;
use App\Http\Controllers\Referentiels\ServiceController;
use App\Http\Controllers\Referentiels\SiteController;
use App\Http\Controllers\Referentiels\TypeVignetteController;
use App\Http\Controllers\Referentiels\VehiculeController;
use App\Http\Controllers\Users\ParametreController;
use App\Http\Controllers\Users\RoleController;
use App\Http\Controllers\Users\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentification (routes publiques : connexion et réinitialisation)
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('login/2fa', [AuthController::class, 'loginDeuxFacteurs']);
    Route::post('mot-de-passe-oublie', [PasswordController::class, 'envoyerLien']);
    Route::post('reinitialiser-mot-de-passe', [PasswordController::class, 'reinitialiser']);
});

/*
|--------------------------------------------------------------------------
| Routes authentifiées
|--------------------------------------------------------------------------
| session.limites : durée maximale absolue de 12 h
| mdp.force       : blocage tant que le mot de passe initial n'est pas changé
*/
Route::middleware(['auth:sanctum', 'session.limites', 'mdp.force'])->group(function () {

    Route::prefix('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('changer-mot-de-passe', [PasswordController::class, 'changer']);

        Route::post('2fa/enroler', [TwoFactorController::class, 'enroler']);
        Route::post('2fa/confirmer', [TwoFactorController::class, 'confirmer']);
        Route::post('2fa/desactiver', [TwoFactorController::class, 'desactiver']);
        Route::post('2fa/codes-secours', [TwoFactorController::class, 'regenererCodesSecours']);
    });

    /*
    |----------------------------------------------------------------------
    | Administration : utilisateurs, rôles, paramètres, journal d'audit
    |----------------------------------------------------------------------
    */
    Route::middleware('permission:utilisateur.consulter')->group(function () {
        Route::get('utilisateurs', [UserController::class, 'index']);
        Route::get('utilisateurs/{user}', [UserController::class, 'show']);
        Route::get('utilisateurs/{user}/connexions', [UserController::class, 'connexions']);
    });
    Route::post('utilisateurs', [UserController::class, 'store'])
        ->middleware('permission:utilisateur.creer');
    Route::put('utilisateurs/{user}', [UserController::class, 'update'])
        ->middleware('permission:utilisateur.modifier');
    Route::post('utilisateurs/{user}/reinitialiser-mdp', [UserController::class, 'reinitialiserMdp'])
        ->middleware('permission:utilisateur.modifier');
    Route::post('utilisateurs/{user}/deverrouiller', [UserController::class, 'deverrouiller'])
        ->middleware('permission:utilisateur.modifier');
    Route::post('utilisateurs/{user}/desactiver', [UserController::class, 'desactiver'])
        ->middleware('permission:utilisateur.desactiver');
    Route::post('utilisateurs/{user}/reactiver', [UserController::class, 'reactiver'])
        ->middleware('permission:utilisateur.desactiver');

    Route::get('roles', [RoleController::class, 'index'])
        ->middleware('permission:utilisateur.consulter');

    Route::get('parametres', [ParametreController::class, 'index'])
        ->middleware('permission:parametre.consulter');
    Route::put('parametres', [ParametreController::class, 'update'])
        ->middleware('permission:parametre.modifier');
    Route::put('roles/{role}/totp-obligatoire', [ParametreController::class, 'totpObligatoire'])
        ->middleware('permission:parametre.modifier');

    Route::get('audit', [AuditLogController::class, 'index'])
        ->middleware('permission:audit.consulter');
    Route::get('audit/export', [AuditLogController::class, 'export'])
        ->middleware(['permission:audit.consulter', 'permission:export.generer']);

    /*
    |----------------------------------------------------------------------
    | Référentiels — chaque contrôleur porte son domaine de permission
    |----------------------------------------------------------------------
    */
    $referentiels = [
        'vehicules' => VehiculeController::class,
        'marques' => \App\Http\Controllers\Referentiels\MarqueController::class,
        'beneficiaires' => BeneficiaireController::class,
        'services' => ServiceController::class,
        'sites' => SiteController::class,
        'types-vignette' => TypeVignetteController::class,
        'coupures' => CoupureController::class,
        'motifs-sortie' => MotifSortieController::class,
        'fournisseurs' => FournisseurController::class,
        'exercices' => ExerciceController::class,
    ];

    foreach ($referentiels as $uri => $controller) {
        $domaine = $controller::DOMAINE;

        Route::middleware("permission:$domaine.consulter")->group(function () use ($uri, $controller) {
            Route::get($uri, [$controller, 'index']);
            Route::get("$uri/{id}", [$controller, 'show'])->whereNumber('id');
        });
        Route::get("$uri-export", [$controller, 'export'])
            ->middleware(["permission:$domaine.consulter", 'permission:export.generer']);
        Route::post($uri, [$controller, 'store'])
            ->middleware("permission:$domaine.creer");
        Route::put("$uri/{id}", [$controller, 'update'])
            ->middleware("permission:$domaine.modifier")->whereNumber('id');
        Route::post("$uri/{id}/desactiver", [$controller, 'desactiver'])
            ->middleware("permission:$domaine.desactiver")->whereNumber('id');
        Route::post("$uri/{id}/reactiver", [$controller, 'reactiver'])
            ->middleware("permission:$domaine.desactiver")->whereNumber('id');
        Route::delete("$uri/{id}", [$controller, 'destroy'])
            ->middleware("permission:$domaine.desactiver")->whereNumber('id');
    }

    /*
    |----------------------------------------------------------------------
    | Import initial Excel / CSV (véhicules, bénéficiaires)
    |----------------------------------------------------------------------
    */
    Route::prefix('import/{type}')->whereIn('type', ['vehicules', 'beneficiaires'])
        ->middleware('permission:referentiel.importer')
        ->group(function () {
            Route::get('modele', [ImportController::class, 'modele']);
            Route::post('previsualiser', [ImportController::class, 'previsualiser']);
            Route::post('valider', [ImportController::class, 'importer']);
        });
});
