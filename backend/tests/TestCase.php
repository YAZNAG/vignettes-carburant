<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Exécute DatabaseSeeder (rôles, permissions, admin, référentiels de base). */
    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Simule une requête émise par la SPA : Sanctum la traite comme
        // "stateful" et attache la session (comme en conditions réelles).
        $this->withHeader('Origin', config('app.frontend_url'));
    }

    /**
     * Change d'identité proprement : Sanctum met les gardes en cache entre
     * deux requêtes simulées, il faut les purger avec la session.
     */
    public function actingAs(\Illuminate\Contracts\Auth\Authenticatable $user, $guard = null): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        return parent::actingAs($user, $guard);
    }

    /** Repart d'un état non authentifié (équivalent d'un nouveau navigateur). */
    protected function resetAuth(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
    }

    protected function creerUtilisateur(string $roleCode, array $attributs = []): User
    {
        static $compteur = 0;
        $compteur++;

        $user = new User;
        // forceFill : certains tests fixent des attributs hors fillable
        // (verrouille_jusqua, echecs_connexion…)
        $user->forceFill(array_merge([
            'nom' => 'Testeur',
            'prenom' => ucfirst($roleCode),
            'email' => "$roleCode$compteur@test.ma",
            'username' => "$roleCode$compteur",
            'password' => 'MotDePasse#123',
            'role_id' => Role::where('code', $roleCode)->value('id'),
            'actif' => true,
            'doit_changer_mdp' => false,
        ], $attributs))->save();

        return $user;
    }

    protected function admin(array $attributs = []): User
    {
        return $this->creerUtilisateur(Role::ADMINISTRATEUR, $attributs);
    }

    protected function gestionnaire(array $attributs = []): User
    {
        return $this->creerUtilisateur(Role::GESTIONNAIRE, $attributs);
    }

    protected function consultation(array $attributs = []): User
    {
        return $this->creerUtilisateur(Role::CONSULTATION, $attributs);
    }
}
