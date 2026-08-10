<?php

namespace Tests\Feature;

use App\Models\Beneficiaire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private function fichierCsv(string $contenu): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('import.csv', $contenu);
    }

    public function test_la_previsualisation_rapporte_les_erreurs_ligne_par_ligne(): void
    {
        Beneficiaire::create(['matricule' => 'B100', 'nom' => 'Existant', 'prenom' => 'Deja']);

        $csv = "Matricule,Nom,Prénom,Fonction,Service,Site,Téléphone\n"
            ."B200,Alaoui,Karim,Chauffeur,,,\n"
            ."B100,Doublon,Exact,,,,\n"          // existe déjà en base
            ."B101,Presque,Pareil,,,,\n"          // quasi-doublon de B100 (distance 1)
            .",SansMatricule,Erreur,,,,\n";       // matricule manquant

        $reponse = $this->actingAs($this->admin())
            ->postJson('/api/import/beneficiaires/previsualiser', ['fichier' => $this->fichierCsv($csv)]);

        $reponse->assertOk()
            ->assertJsonPath('total', 4)
            ->assertJsonPath('erreurs', 2)
            ->assertJsonPath('importables', 2);

        $lignes = collect($reponse->json('lignes'));
        $this->assertNotEmpty($lignes->firstWhere('donnees.matricule', 'B100')['erreurs']);
        $this->assertNotEmpty($lignes->firstWhere('donnees.matricule', 'B101')['avertissements']);
    }

    public function test_l_import_est_atomique_une_erreur_annule_tout(): void
    {
        $csv = "Matricule,Nom,Prénom\n"
            ."B300,Valide,Ligne\n"
            .",Invalide,SansMatricule\n";

        $this->actingAs($this->admin())
            ->postJson('/api/import/beneficiaires/valider', ['fichier' => $this->fichierCsv($csv)])
            ->assertUnprocessable();

        // la ligne valide n'a PAS été insérée : tout ou rien
        $this->assertDatabaseMissing('beneficiaires', ['matricule' => 'B300']);
    }

    public function test_un_import_valide_insere_toutes_les_lignes(): void
    {
        $csv = "Matricule,Nom,Prénom\n"
            ."B400,Alami,Sara\n"
            ."B500,Berrada,Youssef\n";

        $this->actingAs($this->admin())
            ->postJson('/api/import/beneficiaires/valider', ['fichier' => $this->fichierCsv($csv)])
            ->assertOk()
            ->assertJsonPath('inseres', 2);

        $this->assertDatabaseHas('beneficiaires', ['matricule' => 'B400', 'nom' => 'Alami']);
        $this->assertDatabaseHas('beneficiaires', ['matricule' => 'B500']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'import_referentiel']);
    }

    public function test_le_gestionnaire_peut_importer_mais_pas_la_consultation(): void
    {
        $csv = "Matricule,Nom,Prénom\nB600,Test,Droits\n";

        $this->actingAs($this->consultation())
            ->postJson('/api/import/beneficiaires/previsualiser', ['fichier' => $this->fichierCsv($csv)])
            ->assertForbidden();

        $this->actingAs($this->gestionnaire())
            ->postJson('/api/import/beneficiaires/previsualiser', ['fichier' => $this->fichierCsv($csv)])
            ->assertOk();
    }
}
