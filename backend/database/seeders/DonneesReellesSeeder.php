<?php

namespace Database\Seeders;

use App\Models\Beneficiaire;
use App\Models\Exercice;
use App\Models\Marque;
use App\Models\Service;
use App\Models\Site;
use App\Models\TypeVignette;
use App\Models\Vehicule;
use Illuminate\Database\Seeder;

/**
 * Données de reprise extraites des fichiers de l'organisme :
 * - « Situation du parc auto — Modèle 2023 AREP DOE » (marques, véhicules, conducteurs)
 * - « ETAT vignettes Carburant 2026 » (état initial de l'exercice 2026)
 *
 * Idempotent : ré-exécutable sans doublon (php artisan db:seed --class=DonneesReellesSeeder).
 */
class DonneesReellesSeeder extends Seeder
{
    public function run(): void
    {
        // --- Services (colonne « Affectation » du parc auto) ---------------
        $services = [];
        foreach ([
            'DIR' => 'Direction',
            'DAFA' => 'Division des Affaires Financières et Administratives',
            'DT' => 'Division des Travaux',
            'DEP' => 'Division des Etudes et de la Programmation',
            'PA' => 'Parc Auto',
        ] as $code => $libelle) {
            $services[$code] = Service::firstOrCreate(['code' => $code], ['libelle' => $libelle]);
        }

        $rabat = Site::firstOrCreate(['libelle' => 'Rabat'], ['ville' => 'Rabat']);

        // --- Marques --------------------------------------------------------
        $marques = [];
        foreach (['Toyota', 'Volkswagen', 'Dacia'] as $libelle) {
            $marques[$libelle] = Marque::firstOrCreate(['libelle' => $libelle]);
        }

        // --- Bénéficiaires / conducteurs -----------------------------------
        $beneficiaires = [];
        $listeConducteurs = [
            ['B001', 'Directeur', 'Arep', 'Directeur', 'DIR'],
            ['B002', 'Oubaika', 'Samir', 'Chauffeur', 'DAFA'],
            ['B003', 'Elaloui', 'Hamza', 'Chauffeur', 'DT'],
            ['B004', 'Abouchaiba', 'Mohamed Salem', 'Chauffeur', 'DAFA'],
            ['B005', 'Abidi', 'Salah', 'Chauffeur', 'DT'],
            ['B006', 'Elbouhddioui', 'Chakib', 'Chauffeur', 'DEP'],
            ['B007', 'Lafqir', 'Mansour', 'Chauffeur', 'DIR'],
            ['B008', 'Laghbira', 'Ali', 'Chauffeur', 'DAFA'],
            ['B009', 'Directeur', 'Rabat', 'Directeur', 'PA'],
        ];
        foreach ($listeConducteurs as [$matricule, $nom, $prenom, $fonction, $service]) {
            $beneficiaires[$matricule] = Beneficiaire::firstOrCreate(
                ['matricule' => $matricule],
                [
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'fonction' => $fonction,
                    'service_id' => $services[$service]->id,
                    'site_id' => $rabat->id,
                ],
            );
        }

        // --- Véhicules ------------------------------------------------------
        $listeVehicules = [
            // immat, marque, modele, carburant, mise en service, service, conducteur
            ['5884-A-70', 'Toyota', 'RAV4', 'Gasoil', '2018-02-19', 'DIR', 'B001'],
            ['M214131', 'Volkswagen', 'Caddy', 'Gasoil', '2017-12-22', 'DAFA', 'B002'],
            ['M214134', 'Volkswagen', 'Caddy', 'Gasoil', '2017-12-22', 'DT', 'B003'],
            ['M216999', 'Dacia', 'HSDAG3', 'Gasoil', '2018-08-01', 'DAFA', 'B004'],
            ['M227873', 'Volkswagen', 'Caddy', 'Gasoil', '2020-01-30', 'DT', 'B005'],
            ['M227874', 'Volkswagen', 'Caddy', 'Gasoil', '2020-01-30', 'DEP', 'B006'],
            ['M228749', 'Dacia', 'Logan', 'Gasoil', '2020-03-09', 'DIR', 'B007'],
            ['M259220', 'Dacia', 'Logan', 'Essence', '2025-02-10', 'DAFA', 'B008'],
            ['M259219', 'Dacia', 'Duster', 'Essence', '2025-02-10', 'PA', 'B009'],
        ];
        foreach ($listeVehicules as [$immat, $marque, $modele, $carburant, $miseEnService, $service, $conducteur]) {
            Vehicule::firstOrCreate(
                ['immatriculation' => $immat],
                [
                    'marque_id' => $marques[$marque]->id,
                    'modele' => $modele,
                    'type_vehicule' => 'Voiture',
                    'type_carburant' => $carburant,
                    'statut' => 'Actif',
                    'date_mise_en_service' => $miseEnService,
                    'service_id' => $services[$service]->id,
                    'site_id' => $rabat->id,
                    'conducteur_id' => $beneficiaires[$conducteur]->id,
                ],
            );
        }

        // --- État initial de l'exercice 2026 -------------------------------
        // « Disponible au 31/12/2025 » du fichier Excel : 227 430 DH en
        // vignettes carburant ; e-vignettes et tickets à zéro (ajustables).
        TypeVignette::where('code', 'VP')->update(['libelle' => 'Vignette carburant']);
        TypeVignette::firstOrCreate(['code' => 'TK'], ['libelle' => 'Ticket']);

        $exercice = Exercice::firstOrCreate(
            ['annee' => 2026],
            [
                'libelle' => 'Exercice 2026',
                'date_debut' => '2026-01-01',
                'date_fin' => '2026-12-31',
                'statut' => Exercice::OUVERT,
            ],
        );

        $soldes = ['VP' => 227430.00, 'EV' => 0.00, 'TK' => 0.00];
        foreach ($soldes as $code => $montant) {
            $type = TypeVignette::where('code', $code)->first();
            if ($type) {
                $exercice->soldes()->firstOrCreate(
                    ['type_vignette_id' => $type->id],
                    ['solde_initial' => $montant],
                );
            }
        }

        $exercice->forceFill([
            'stock_initial' => $exercice->soldes()->sum('solde_initial'),
        ])->save();
    }
}
