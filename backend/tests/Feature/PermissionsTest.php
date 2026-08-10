<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    private array $vehiculeValide = [
        'immatriculation' => 'M999888',
        'type_vehicule' => 'Voiture',
        'type_carburant' => 'Gasoil',
        'statut' => 'Actif',
    ];

    public function test_le_role_consultation_recoit_403_sur_une_route_de_creation(): void
    {
        // appel direct de l'API (équivalent client HTTP, hors interface)
        $this->actingAs($this->consultation())
            ->postJson('/api/vehicules', $this->vehiculeValide)
            ->assertForbidden();

        $this->assertDatabaseMissing('vehicules', ['immatriculation' => 'M999888']);
    }

    public function test_tout_acces_refuse_est_journalise_dans_l_audit(): void
    {
        $this->actingAs($this->consultation())
            ->postJson('/api/vehicules', $this->vehiculeValide)
            ->assertForbidden();

        $this->assertDatabaseHas('audit_logs', ['action' => 'acces_refuse']);
    }

    public function test_matrice_des_roles_sur_les_referentiels(): void
    {
        // Consultation : lecture seule
        $this->actingAs($this->consultation())->getJson('/api/vehicules')->assertOk();

        // Gestionnaire : créer/modifier mais PAS désactiver
        $gestionnaire = $this->gestionnaire();
        $creation = $this->actingAs($gestionnaire)
            ->postJson('/api/vehicules', $this->vehiculeValide)
            ->assertCreated();

        $id = $creation->json('id');
        $this->actingAs($gestionnaire)
            ->postJson("/api/vehicules/$id/desactiver")
            ->assertForbidden();

        // Administrateur : peut désactiver (mais jamais utilisé → suppression suggérée)
        $this->actingAs($this->admin())
            ->postJson("/api/vehicules/$id/desactiver")
            ->assertStatus(409)
            ->assertJsonPath('suppression_possible', true);
    }

    public function test_le_gestionnaire_n_accede_pas_aux_utilisateurs_ni_a_l_audit(): void
    {
        $gestionnaire = $this->gestionnaire();

        $this->actingAs($gestionnaire)->getJson('/api/utilisateurs')->assertForbidden();
        $this->actingAs($gestionnaire)->getJson('/api/audit')->assertForbidden();
    }

    public function test_la_consultation_accede_au_journal_d_audit(): void
    {
        $this->actingAs($this->consultation())->getJson('/api/audit')->assertOk();
    }

    public function test_le_valideur_ne_cree_pas_de_referentiel(): void
    {
        $valideur = $this->creerUtilisateur('valideur');

        $this->actingAs($valideur)->getJson('/api/vehicules')->assertOk();
        $this->actingAs($valideur)->postJson('/api/vehicules', $this->vehiculeValide)->assertForbidden();
        $this->actingAs($valideur)
            ->postJson('/api/services', ['libelle' => 'Interdit', 'code' => 'INT'])
            ->assertForbidden();
    }
}
