<?php

namespace Tests\Feature;

use App\Models\LoginAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthentificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_utilisateur_non_authentifie_ne_peut_acceder_a_aucune_route_protegee(): void
    {
        $this->getJson('/api/vehicules')->assertUnauthorized();
        $this->getJson('/api/utilisateurs')->assertUnauthorized();
        $this->getJson('/api/audit')->assertUnauthorized();
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_connexion_par_email_ou_username_insensible_a_la_casse(): void
    {
        $this->creerUtilisateur('gestionnaire', [
            'email' => 'karim@test.ma',
            'username' => 'karim.g',
        ]);

        $this->postJson('/api/auth/login', [
            'identifiant' => 'KARIM@TEST.MA',
            'password' => 'MotDePasse#123',
        ])->assertOk()->assertJsonPath('utilisateur.username', 'karim.g');

        $this->postJson('/api/auth/logout');

        $this->postJson('/api/auth/login', [
            'identifiant' => 'Karim.G',
            'password' => 'MotDePasse#123',
        ])->assertOk();
    }

    public function test_message_generique_en_cas_d_identifiants_incorrects(): void
    {
        $this->creerUtilisateur('gestionnaire', ['username' => 'existant']);

        $reponseCompteExistant = $this->postJson('/api/auth/login', [
            'identifiant' => 'existant', 'password' => 'Mauvais#123',
        ]);
        $reponseCompteInconnu = $this->postJson('/api/auth/login', [
            'identifiant' => 'inconnu', 'password' => 'Mauvais#123',
        ]);

        // même statut, même message : pas d'énumération de comptes
        $reponseCompteExistant->assertUnprocessable();
        $reponseCompteInconnu->assertUnprocessable();
        $this->assertSame(
            $reponseCompteExistant->json('errors.identifiant'),
            $reponseCompteInconnu->json('errors.identifiant'),
        );
    }

    public function test_six_tentatives_erronees_verrouillent_le_compte(): void
    {
        $user = $this->creerUtilisateur('gestionnaire', ['username' => 'victime']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'identifiant' => 'victime', 'password' => 'Mauvais#123',
            ])->assertUnprocessable();
        }

        // 6e tentative : compte verrouillé, même avec le BON mot de passe
        $this->postJson('/api/auth/login', [
            'identifiant' => 'victime', 'password' => 'MotDePasse#123',
        ])->assertStatus(423);

        $this->assertTrue($user->fresh()->estVerrouille());
    }

    public function test_le_verrouillage_expire_apres_15_minutes(): void
    {
        $user = $this->creerUtilisateur('gestionnaire', [
            'username' => 'patiente',
            'verrouille_jusqua' => now()->addMinutes(15),
        ]);

        $this->postJson('/api/auth/login', [
            'identifiant' => 'patiente', 'password' => 'MotDePasse#123',
        ])->assertStatus(423);

        $this->travel(16)->minutes();

        $this->postJson('/api/auth/login', [
            'identifiant' => 'patiente', 'password' => 'MotDePasse#123',
        ])->assertOk();
    }

    public function test_chaque_tentative_est_journalisee(): void
    {
        $this->creerUtilisateur('gestionnaire', ['username' => 'trace']);

        $this->postJson('/api/auth/login', ['identifiant' => 'trace', 'password' => 'Mauvais#123']);
        $this->postJson('/api/auth/login', ['identifiant' => 'trace', 'password' => 'MotDePasse#123']);

        $this->assertDatabaseHas('login_attempts', ['identifiant' => 'trace', 'succes' => false]);
        $this->assertDatabaseHas('login_attempts', ['identifiant' => 'trace', 'succes' => true]);
        $this->assertNotNull(LoginAttempt::where('identifiant', 'trace')->first()->ip_address);
    }

    public function test_un_compte_desactive_ne_peut_pas_se_connecter(): void
    {
        $this->creerUtilisateur('gestionnaire', ['username' => 'ferme', 'actif' => false]);

        $this->postJson('/api/auth/login', [
            'identifiant' => 'ferme', 'password' => 'MotDePasse#123',
        ])->assertUnprocessable();
    }

    public function test_changement_de_mot_de_passe_force_avant_tout_acces(): void
    {
        $user = $this->creerUtilisateur('gestionnaire', ['doit_changer_mdp' => true]);

        $this->actingAs($user)
            ->getJson('/api/vehicules')
            ->assertForbidden()
            ->assertJsonPath('code', 'MDP_A_CHANGER');

        // la route de changement reste accessible
        $this->actingAs($user)
            ->postJson('/api/auth/changer-mot-de-passe', [
                'mot_de_passe_actuel' => 'MotDePasse#123',
                'nouveau_mot_de_passe' => 'NouveauMdp#456',
                'nouveau_mot_de_passe_confirmation' => 'NouveauMdp#456',
            ])->assertOk();

        $this->actingAs($user->fresh())->getJson('/api/vehicules')->assertOk();
    }

    public function test_la_connexion_regenere_la_session_et_est_auditee(): void
    {
        $this->creerUtilisateur('gestionnaire', ['username' => 'audite']);

        $this->postJson('/api/auth/login', [
            'identifiant' => 'audite', 'password' => 'MotDePasse#123',
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'connexion']);
    }
}
