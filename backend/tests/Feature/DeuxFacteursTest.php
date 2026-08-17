<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class DeuxFacteursTest extends TestCase
{
    use RefreshDatabase;

    private function activerTotp(User $user): string
    {
        $reponse = $this->actingAs($user)->postJson('/api/auth/2fa/enroler')->assertOk();
        $secret = $reponse->json('secret');

        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $confirmation = $this->actingAs($user)
            ->postJson('/api/auth/2fa/confirmer', ['code' => $code])
            ->assertOk();

        $this->assertCount(8, $confirmation->json('codes_secours'));

        return $secret;
    }

    public function test_enrolement_confirmation_et_connexion_en_deux_etapes(): void
    {
        $user = $this->gestionnaire(['username' => 'totp.user']);
        $secret = $this->activerTotp($user);

        $this->resetAuth();

        // étape 1 : mot de passe correct → défi 2FA, pas encore authentifié
        $this->postJson('/api/auth/login', [
            'identifiant' => 'totp.user', 'password' => 'MotDePasse#123',
        ])->assertOk()->assertJsonPath('etape', '2fa');

        $this->getJson('/api/auth/me')->assertUnauthorized();

        // étape 2 : mauvais code refusé, bon code accepté
        $this->postJson('/api/auth/login/2fa', ['code' => '000000'])->assertUnprocessable();

        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $this->postJson('/api/auth/login/2fa', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('utilisateur.username', 'totp.user');
    }

    public function test_un_code_de_secours_ne_fonctionne_qu_une_fois(): void
    {
        $user = $this->gestionnaire(['username' => 'secours.user']);

        $this->actingAs($user)->postJson('/api/auth/2fa/enroler')->assertOk();
        $secret = $user->fresh()->totp_secret;
        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $codesSecours = $this->actingAs($user)
            ->postJson('/api/auth/2fa/confirmer', ['code' => $code])
            ->json('codes_secours');

        $this->resetAuth();

        $connexion = fn () => $this->postJson('/api/auth/login', [
            'identifiant' => 'secours.user', 'password' => 'MotDePasse#123',
        ]);

        // le code de secours passe une fois
        $connexion()->assertJsonPath('etape', '2fa');
        $this->postJson('/api/auth/login/2fa', ['code' => $codesSecours[0]])->assertOk();

        $this->resetAuth();

        // et une seule
        $connexion()->assertJsonPath('etape', '2fa');
        $this->postJson('/api/auth/login/2fa', ['code' => $codesSecours[0]])->assertUnprocessable();
    }

    public function test_la_desactivation_est_refusee_si_le_role_impose_la_2fa(): void
    {
        // la 2FA n'est plus imposée par défaut : on l'impose ici pour tester le mécanisme
        \App\Models\Role::where('code', \App\Models\Role::ADMINISTRATEUR)
            ->update(['totp_obligatoire' => true]);

        $admin = $this->admin();
        $secret = $this->activerTotp($admin);

        $this->actingAs($admin)->postJson('/api/auth/2fa/desactiver', [
            'password' => 'MotDePasse#123',
            'code' => app(Google2FA::class)->getCurrentOtp($secret),
        ])->assertForbidden();
    }
}
