<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UtilisateursTest extends TestCase
{
    use RefreshDatabase;

    public function test_impossible_de_desactiver_le_dernier_administrateur_actif(): void
    {
        $admin = $this->admin();

        // désactive le compte admin seedé pour que $admin devienne le dernier
        User::where('username', 'admin')->update(['actif' => false]);

        $autreAdmin = $this->admin();

        // $autreAdmin désactive $admin : possible (il en reste un)
        $this->actingAs($autreAdmin)
            ->postJson("/api/utilisateurs/{$admin->id}/desactiver")
            ->assertOk();

        // plus personne ne peut désactiver le dernier admin actif
        $this->actingAs($autreAdmin)
            ->postJson("/api/utilisateurs/{$autreAdmin->id}/desactiver")
            ->assertForbidden(); // c'est aussi son propre compte

        $troisieme = $this->admin();
        $this->actingAs($troisieme)
            ->postJson("/api/utilisateurs/{$autreAdmin->id}/desactiver")
            ->assertOk(); // il reste $troisieme

        $this->actingAs($troisieme)
            ->postJson("/api/utilisateurs/{$troisieme->id}/desactiver")
            ->assertForbidden();
    }

    public function test_un_administrateur_ne_peut_pas_modifier_son_propre_role(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->putJson("/api/utilisateurs/{$admin->id}", [
            'nom' => $admin->nom,
            'prenom' => $admin->prenom,
            'email' => $admin->email,
            'username' => $admin->username,
            'role_id' => Role::where('code', Role::CONSULTATION)->value('id'),
        ])->assertForbidden();
    }

    public function test_un_compte_cree_par_l_admin_se_connecte_directement(): void
    {
        $reponse = $this->actingAs($this->admin())->postJson('/api/utilisateurs', [
            'nom' => 'Nouveau',
            'prenom' => 'Compte',
            'email' => 'nouveau@test.ma',
            'username' => 'nouveau',
            'role_id' => Role::where('code', Role::GESTIONNAIRE)->value('id'),
            'mot_de_passe_initial' => 'Provisoire#123',
        ]);

        $reponse->assertCreated();
        // pas de changement forcé : accès direct au tableau de bord
        $this->assertFalse(User::find($reponse->json('id'))->doit_changer_mdp);
    }

    public function test_deverrouillage_manuel_d_un_compte(): void
    {
        $bloque = $this->gestionnaire(['verrouille_jusqua' => now()->addMinutes(10)]);

        $this->actingAs($this->admin())
            ->postJson("/api/utilisateurs/{$bloque->id}/deverrouiller")
            ->assertOk();

        $this->assertFalse($bloque->fresh()->estVerrouille());
        $this->assertDatabaseHas('audit_logs', ['action' => 'deverrouillage_compte']);
    }

    public function test_la_reinitialisation_admin_force_le_changement(): void
    {
        $cible = $this->gestionnaire();

        $this->actingAs($this->admin())
            ->postJson("/api/utilisateurs/{$cible->id}/reinitialiser-mdp", [
                'nouveau_mot_de_passe' => 'Provisoire#456',
            ])->assertOk();

        $this->assertTrue($cible->fresh()->doit_changer_mdp);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reinitialisation_mdp_admin']);
    }

    public function test_creation_et_modification_d_utilisateur_sont_auditees(): void
    {
        $reponse = $this->actingAs($this->admin())->postJson('/api/utilisateurs', [
            'nom' => 'Trace',
            'prenom' => 'Audit',
            'email' => 'trace@test.ma',
            'username' => 'trace.audit',
            'role_id' => Role::where('code', Role::CONSULTATION)->value('id'),
            'mot_de_passe_initial' => 'Provisoire#123',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'creation',
            'entite_type' => 'User',
            'entite_id' => $reponse->json('id'),
        ]);
    }

    public function test_le_mot_de_passe_n_apparait_jamais_dans_l_audit(): void
    {
        $this->actingAs($this->admin())->postJson('/api/utilisateurs', [
            'nom' => 'Secret',
            'prenom' => 'Bien',
            'email' => 'secret@test.ma',
            'username' => 'secret',
            'role_id' => Role::where('code', Role::CONSULTATION)->value('id'),
            'mot_de_passe_initial' => 'Provisoire#123',
        ]);

        $entrees = \DB::table('audit_logs')->where('entite_type', 'User')->get();
        foreach ($entrees as $entree) {
            $this->assertStringNotContainsString('Provisoire#123', (string) $entree->apres);
            $this->assertStringNotContainsString('password', (string) $entree->apres);
        }
    }
}
