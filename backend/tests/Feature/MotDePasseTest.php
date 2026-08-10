<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ReinitialisationMotDePasse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MotDePasseTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_politique_de_mot_de_passe_est_appliquee(): void
    {
        $user = $this->gestionnaire();

        $essayer = fn (string $mdp) => $this->actingAs($user)
            ->postJson('/api/auth/changer-mot-de-passe', [
                'mot_de_passe_actuel' => 'MotDePasse#123',
                'nouveau_mot_de_passe' => $mdp,
                'nouveau_mot_de_passe_confirmation' => $mdp,
            ]);

        $essayer('Court1a')->assertUnprocessable();          // trop court
        $essayer('touminuscule1')->assertUnprocessable();    // pas de majuscule
        $essayer('TOUTMAJUSCULE1')->assertUnprocessable();   // pas de minuscule
        $essayer('SansChiffreIci')->assertUnprocessable();   // pas de chiffre
        $essayer('Password123')->assertUnprocessable();      // mot de passe courant
        $essayer('Valide#2026ok')->assertOk();
    }

    public function test_le_changement_exige_le_mot_de_passe_actuel(): void
    {
        $this->actingAs($this->gestionnaire())
            ->postJson('/api/auth/changer-mot-de-passe', [
                'mot_de_passe_actuel' => 'Mauvais#123',
                'nouveau_mot_de_passe' => 'Valide#2026ok',
                'nouveau_mot_de_passe_confirmation' => 'Valide#2026ok',
            ])->assertUnprocessable();
    }

    public function test_interdiction_de_reutiliser_les_trois_derniers_mots_de_passe(): void
    {
        $user = $this->gestionnaire();

        $changer = function (string $actuel, string $nouveau) use ($user) {
            return $this->actingAs($user->fresh())
                ->postJson('/api/auth/changer-mot-de-passe', [
                    'mot_de_passe_actuel' => $actuel,
                    'nouveau_mot_de_passe' => $nouveau,
                    'nouveau_mot_de_passe_confirmation' => $nouveau,
                ]);
        };

        $changer('MotDePasse#123', 'Deuxieme#2026a')->assertOk();
        $changer('Deuxieme#2026a', 'Troisieme#2026b')->assertOk();

        // réutilisation du tout premier : refusée (il est dans les 3 derniers)
        $changer('Troisieme#2026b', 'MotDePasse#123')->assertUnprocessable();

        // réutilisation du mot de passe actuel : refusée
        $changer('Troisieme#2026b', 'Troisieme#2026b')->assertUnprocessable();
    }

    public function test_la_reponse_de_mot_de_passe_oublie_est_identique_que_l_email_existe_ou_non(): void
    {
        Notification::fake();
        $this->gestionnaire(['email' => 'connu@test.ma']);

        $reponseExistant = $this->postJson('/api/auth/mot-de-passe-oublie', ['email' => 'connu@test.ma']);
        $reponseInconnu = $this->postJson('/api/auth/mot-de-passe-oublie', ['email' => 'inconnu@test.ma']);

        $this->assertSame($reponseExistant->json('message'), $reponseInconnu->json('message'));
    }

    public function test_un_jeton_de_reinitialisation_utilise_deux_fois_est_refuse(): void
    {
        Notification::fake();
        $user = $this->gestionnaire(['email' => 'reset@test.ma']);

        $this->postJson('/api/auth/mot-de-passe-oublie', ['email' => 'reset@test.ma']);

        $token = null;
        Notification::assertSentTo($user, ReinitialisationMotDePasse::class,
            function ($notification) use (&$token) {
                $token = $notification->token;

                return true;
            });

        $donnees = [
            'token' => $token,
            'email' => 'reset@test.ma',
            'password' => 'ApresReset#99',
            'password_confirmation' => 'ApresReset#99',
        ];

        // première utilisation : acceptée
        $this->postJson('/api/auth/reinitialiser-mot-de-passe', $donnees)->assertOk();

        // seconde utilisation du même jeton : refusée
        $donnees['password'] = $donnees['password_confirmation'] = 'AutreMdp#100';
        $this->postJson('/api/auth/reinitialiser-mot-de-passe', $donnees)->assertUnprocessable();
    }

    public function test_la_reinitialisation_invalide_les_sessions_et_deverrouille(): void
    {
        Notification::fake();
        $user = $this->gestionnaire([
            'email' => 'verrou@test.ma',
            'verrouille_jusqua' => now()->addMinutes(10),
        ]);

        $this->postJson('/api/auth/mot-de-passe-oublie', ['email' => 'verrou@test.ma']);

        $token = null;
        Notification::assertSentTo($user, ReinitialisationMotDePasse::class,
            function ($notification) use (&$token) {
                $token = $notification->token;

                return true;
            });

        $this->postJson('/api/auth/reinitialiser-mot-de-passe', [
            'token' => $token,
            'email' => 'verrou@test.ma',
            'password' => 'ApresReset#99',
            'password_confirmation' => 'ApresReset#99',
        ])->assertOk();

        $this->assertFalse($user->fresh()->estVerrouille());
        $this->assertDatabaseHas('audit_logs', ['action' => 'reinitialisation_mdp']);
    }
}
