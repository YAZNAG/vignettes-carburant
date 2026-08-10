<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\Vehicule;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReferentielsTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_unicite_des_immatriculations_est_garantie_par_la_base_de_donnees(): void
    {
        Vehicule::create(['immatriculation' => 'M214134']);

        // insertion brute, en contournant toute validation applicative
        $this->expectException(QueryException::class);
        DB::table('vehicules')->insert([
            'immatriculation' => 'm214134', // même valeur, casse différente : refusée aussi (citext)
            'type_vehicule' => 'Voiture',
            'type_carburant' => 'Gasoil',
            'statut' => 'Actif',
            'actif' => true,
        ]);
    }

    public function test_la_saisie_d_une_immatriculation_tres_proche_declenche_l_avertissement(): void
    {
        Vehicule::create(['immatriculation' => 'M2214134']);
        $gestionnaire = $this->gestionnaire();

        // M214134 vs M2214134 : distance de Levenshtein 1 → avertissement bloquant
        $reponse = $this->actingAs($gestionnaire)->postJson('/api/vehicules', [
            'immatriculation' => 'M214134',
            'type_vehicule' => 'Voiture',
            'type_carburant' => 'Gasoil',
            'statut' => 'Actif',
        ]);

        $reponse->assertStatus(409)
            ->assertJsonPath('confirmation_requise', true)
            ->assertJsonPath('similaires.0.valeur', 'M2214134');

        $this->assertDatabaseMissing('vehicules', ['immatriculation' => 'M214134']);

        // avec confirmation explicite : création acceptée
        $this->actingAs($gestionnaire)->postJson('/api/vehicules', [
            'immatriculation' => 'M214134',
            'type_vehicule' => 'Voiture',
            'type_carburant' => 'Gasoil',
            'statut' => 'Actif',
            'confirmer_similaire' => true,
        ])->assertCreated();
    }

    public function test_la_normalisation_a_la_saisie(): void
    {
        $reponse = $this->actingAs($this->gestionnaire())->postJson('/api/vehicules', [
            'immatriculation' => '  m 214 134  ',
            'marque' => '  Dacia   Duster  ',
            'type_vehicule' => 'Voiture',
            'type_carburant' => 'Gasoil',
            'statut' => 'Actif',
        ]);

        $reponse->assertCreated();
        $this->assertDatabaseHas('vehicules', [
            'immatriculation' => 'M214134',
            'marque' => 'Dacia Duster',
        ]);
    }

    public function test_la_recherche_ignore_les_accents_et_la_casse(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postJson('/api/beneficiaires', [
            'matricule' => 'B100',
            'nom' => 'ELALAOUI',
            'prenom' => 'Karim',
        ])->assertCreated();

        Site::create(['libelle' => 'Laâyoune', 'ville' => 'Laâyoune']);

        // « elalaoui » doit trouver « Elalaoui » (capitalisé à la saisie)
        $this->actingAs($admin)->getJson('/api/beneficiaires?recherche=elalaoui')
            ->assertOk()->assertJsonPath('data.0.matricule', 'B100');

        // « laayoune » sans accent doit trouver « Laâyoune »
        $this->actingAs($admin)->getJson('/api/sites?recherche=laayoune')
            ->assertOk()->assertJsonPath('data.0.libelle', 'Laâyoune');
    }

    public function test_capitalisation_automatique_des_noms(): void
    {
        $this->actingAs($this->admin())->postJson('/api/beneficiaires', [
            'matricule' => 'B200',
            'nom' => 'ELBOUHDDIOUI',
            'prenom' => 'fatima',
        ])->assertCreated();

        $this->assertDatabaseHas('beneficiaires', [
            'matricule' => 'B200',
            'nom' => 'Elbouhddioui',
            'prenom' => 'Fatima',
        ]);
    }

    public function test_desactivation_refusee_si_jamais_servi_et_suppression_permise(): void
    {
        $admin = $this->admin();
        $id = $this->actingAs($admin)
            ->postJson('/api/services', ['libelle' => 'Éphémère', 'code' => 'EPH'])
            ->assertCreated()->json('id');

        // jamais utilisé : désactivation refusée, suppression proposée
        $this->actingAs($admin)->postJson("/api/services/$id/desactiver")
            ->assertStatus(409)->assertJsonPath('suppression_possible', true);

        $this->actingAs($admin)->deleteJson("/api/services/$id")->assertOk();
        $this->assertDatabaseMissing('services', ['id' => $id]);
    }

    public function test_un_element_utilise_ne_peut_etre_que_desactive(): void
    {
        $admin = $this->admin();
        $serviceId = $this->actingAs($admin)
            ->postJson('/api/services', ['libelle' => 'Garage', 'code' => 'GAR'])
            ->json('id');

        // le service est référencé par un véhicule → il a servi
        $this->actingAs($admin)->postJson('/api/vehicules', [
            'immatriculation' => 'M555000',
            'type_vehicule' => 'Voiture',
            'type_carburant' => 'Gasoil',
            'statut' => 'Actif',
            'service_id' => $serviceId,
        ])->assertCreated();

        $this->actingAs($admin)->deleteJson("/api/services/$serviceId")->assertStatus(409);

        $this->actingAs($admin)->postJson("/api/services/$serviceId/desactiver")->assertOk();

        // désactivé : absent des listes de saisie (actif=true), présent en consultation
        $this->actingAs($admin)->getJson('/api/services?actif=true')
            ->assertOk()->assertJsonMissing(['code' => 'GAR']);
        $this->actingAs($admin)->getJson('/api/services')
            ->assertOk()->assertJsonFragment(['code' => 'GAR']);
    }

    public function test_impossible_de_desactiver_un_type_de_vignette_avec_coupures_actives(): void
    {
        $admin = $this->admin();
        $typeId = DB::table('types_vignette')->where('code', 'VP')->value('id');

        $this->actingAs($admin)->postJson("/api/types-vignette/$typeId/desactiver")
            ->assertStatus(409);
    }

    public function test_un_seul_exercice_ouvert_a_la_fois(): void
    {
        // l'exercice 2026 seedé est ouvert ; l'unicité est garantie par la base
        $this->expectException(QueryException::class);
        DB::table('exercices')->insert([
            'annee' => 2027,
            'libelle' => 'Exercice 2027',
            'date_debut' => '2027-01-01',
            'date_fin' => '2027-12-31',
            'stock_initial' => 0,
            'statut' => 'ouvert',
        ]);
    }

    public function test_les_modifications_de_referentiel_sont_auditees_avec_avant_apres(): void
    {
        $admin = $this->admin();
        $id = $this->actingAs($admin)->postJson('/api/vehicules', [
            'immatriculation' => 'M777000',
            'type_vehicule' => 'Voiture',
            'type_carburant' => 'Gasoil',
            'statut' => 'Actif',
        ])->json('id');

        $this->actingAs($admin)->putJson("/api/vehicules/$id", [
            'immatriculation' => 'M777000',
            'type_vehicule' => 'Voiture',
            'type_carburant' => 'Essence',
            'statut' => 'En réparation',
        ])->assertOk();

        $entree = DB::table('audit_logs')
            ->where('entite_type', 'Vehicule')
            ->where('entite_id', $id)
            ->where('action', 'modification')
            ->first();

        $this->assertNotNull($entree);
        $avant = json_decode($entree->avant, true);
        $apres = json_decode($entree->apres, true);
        $this->assertSame('Gasoil', $avant['type_carburant']);
        $this->assertSame('Essence', $apres['type_carburant']);
        $this->assertSame('Actif', $avant['statut']);
        $this->assertSame('En réparation', $apres['statut']);
    }
}
