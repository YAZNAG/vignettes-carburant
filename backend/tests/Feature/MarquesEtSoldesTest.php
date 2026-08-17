<?php

namespace Tests\Feature;

use App\Models\Marque;
use App\Models\TypeVignette;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarquesEtSoldesTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_marques_sont_un_referentiel_normalise(): void
    {
        $admin = $this->admin();

        $reponse = $this->actingAs($admin)
            ->postJson('/api/marques', ['libelle' => '  TOYOTA  '])
            ->assertCreated();

        // capitalisation automatique
        $this->assertDatabaseHas('marques', ['libelle' => 'Toyota']);

        // un véhicule référence la marque par son id (plus de texte libre)
        $this->actingAs($admin)->postJson('/api/vehicules', [
            'immatriculation' => 'M300300',
            'marque_id' => $reponse->json('id'),
            'type_vehicule' => 'Voiture',
            'type_carburant' => 'Gasoil',
            'statut' => 'Actif',
        ])->assertCreated();

        $this->assertDatabaseHas('vehicules', [
            'immatriculation' => 'M300300',
            'marque_id' => $reponse->json('id'),
        ]);

        // une marque utilisée ne peut pas être supprimée, seulement désactivée
        $this->actingAs($admin)->deleteJson('/api/marques/'.$reponse->json('id'))->assertStatus(409);
        $this->actingAs($admin)->postJson('/api/marques/'.$reponse->json('id').'/desactiver')->assertOk();
    }

    public function test_le_seeder_des_donnees_reelles_charge_le_parc_et_l_etat_initial(): void
    {
        $this->seed(\Database\Seeders\DonneesReellesSeeder::class);

        $this->assertSame(3, Marque::count());                       // Toyota, Volkswagen, Dacia
        $this->assertSame(9, Vehicule::count());                     // parc AREP DOE
        $this->assertSame(9, DB::table('beneficiaires')->count());   // conducteurs

        // rejouable sans doublon
        $this->seed(\Database\Seeders\DonneesReellesSeeder::class);
        $this->assertSame(9, Vehicule::count());

        // état initial 2026 : 227 430 DH en vignettes carburant, 0 ailleurs
        $exercice = \App\Models\Exercice::where('annee', 2026)->first();
        $soldes = $exercice->soldes()->with('typeVignette')->get()
            ->mapWithKeys(fn ($s) => [$s->typeVignette->code => (float) $s->solde_initial]);

        $this->assertSame(227430.0, $soldes['VP']);
        $this->assertSame(0.0, $soldes['EV']);
        $this->assertSame(0.0, $soldes['TK']);
        $this->assertSame(227430.0, (float) $exercice->stock_initial);
    }

    public function test_creation_d_exercice_avec_soldes_par_type(): void
    {
        $types = TypeVignette::pluck('id', 'code');

        $reponse = $this->actingAs($this->admin())->postJson('/api/exercices', [
            'annee' => 2027,
            'libelle' => 'Exercice 2027',
            'date_debut' => '2027-01-01',
            'date_fin' => '2027-12-31',
            'soldes' => [
                ['type_vignette_id' => $types['VP'], 'solde_initial' => 1000],
                ['type_vignette_id' => $types['EV'], 'solde_initial' => 500],
                ['type_vignette_id' => $types['TK'], 'solde_initial' => 250],
            ],
        ]);

        $reponse->assertCreated();
        // le stock initial global est la somme des soldes par type
        $this->assertSame(1750.0, (float) \App\Models\Exercice::find($reponse->json('id'))->stock_initial);
        $this->assertCount(3, $reponse->json('soldes'));
    }
}
