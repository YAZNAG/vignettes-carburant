<?php

namespace Database\Seeders;

use App\Models\Exercice;
use App\Models\MotifSortie;
use App\Models\Parametre;
use App\Models\Role;
use App\Models\TypeVignette;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        // --- Compte administrateur initial (changement de mdp forcé) -------
        $admin = User::query()->firstOrCreate(
            ['username' => 'admin'],
            [
                'nom' => 'Administrateur',
                'prenom' => 'Compte',
                'email' => 'admin@organisme.ma',
                'password' => Hash::make(env('SEED_ADMIN_PASSWORD', 'Admin#2026!vignettes')),
                'role_id' => Role::where('code', Role::ADMINISTRATEUR)->value('id'),
                'actif' => true,
                'doit_changer_mdp' => false,
            ],
        );

        // --- Types de vignette et coupures ---------------------------------
        $papier = TypeVignette::firstOrCreate(['code' => 'VP'], ['libelle' => 'Vignette papier']);
        $evignette = TypeVignette::firstOrCreate(['code' => 'EV'], ['libelle' => 'E-vignette']);

        foreach ([100, 200, 500, 1000] as $valeur) {
            $papier->coupures()->firstOrCreate(['valeur' => $valeur]);
            $evignette->coupures()->firstOrCreate(['valeur' => $valeur]);
        }

        // --- Motifs de sortie ----------------------------------------------
        $motifs = [
            ['code' => 'DOT', 'libelle' => 'Dotation', 'necessite_validation' => false, 'soumis_plafond' => true],
            ['code' => 'MIS', 'libelle' => 'Mission', 'necessite_validation' => true, 'soumis_plafond' => false],
            ['code' => 'AVD', 'libelle' => 'Avance sur dotation', 'necessite_validation' => true, 'soumis_plafond' => true],
            ['code' => 'EXC', 'libelle' => 'Exceptionnel', 'necessite_validation' => true, 'soumis_plafond' => false],
        ];
        foreach ($motifs as $motif) {
            MotifSortie::firstOrCreate(['code' => $motif['code']], $motif);
        }

        // --- Exercice budgétaire 2026 --------------------------------------
        Exercice::firstOrCreate(
            ['annee' => 2026],
            [
                'libelle' => 'Exercice 2026',
                'date_debut' => '2026-01-01',
                'date_fin' => '2026-12-31',
                'stock_initial' => 0,
                'statut' => Exercice::OUVERT,
            ],
        );

        // --- Paramètres généraux -------------------------------------------
        $parametres = [
            [Parametre::NOM_ORGANISME, 'Organisme', "Nom de l'organisme"],
            [Parametre::LOGO_PATH, null, 'Logo (états PDF)'],
            [Parametre::DUREE_INACTIVITE, '30', 'Durée d\'inactivité avant déconnexion (minutes)'],
            [Parametre::SEUIL_ALERTE_STOCK, '5000', 'Seuil d\'alerte de stock bas (DH)'],
            [Parametre::FORMAT_NUMERO_PIECE, 'VC-{annee}-{numero:5}', 'Format des numéros de pièce'],
        ];
        foreach ($parametres as [$cle, $valeur, $libelle]) {
            Parametre::firstOrCreate(['cle' => $cle], ['valeur' => $valeur, 'libelle' => $libelle]);
        }
    }
}
