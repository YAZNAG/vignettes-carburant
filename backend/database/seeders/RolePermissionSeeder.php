<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /** Domaines de référentiel soumis à la matrice consulter/creer/modifier/desactiver. */
    private const DOMAINES_REFERENTIEL = [
        'vehicule' => 'Véhicules',
        'marque' => 'Marques',
        'beneficiaire' => 'Bénéficiaires',
        'type_vignette' => 'Types de vignette',
        'coupure' => 'Coupures',
        'motif_sortie' => 'Motifs de sortie',
        'fournisseur' => 'Fournisseurs',
        'exercice' => 'Exercices budgétaires',
        'service' => 'Services',
        'site' => 'Sites',
    ];

    public function run(): void
    {
        $permissions = [];

        $ajouter = function (string $code, string $libelle, string $domaine) use (&$permissions) {
            $permissions[$code] = Permission::updateOrCreate(
                ['code' => $code],
                ['libelle' => $libelle, 'domaine' => $domaine],
            );
        };

        // Sécurité et administration
        $ajouter('utilisateur.consulter', 'Consulter les utilisateurs', 'utilisateur');
        $ajouter('utilisateur.creer', 'Créer un utilisateur', 'utilisateur');
        $ajouter('utilisateur.modifier', 'Modifier un utilisateur', 'utilisateur');
        $ajouter('utilisateur.desactiver', 'Désactiver un utilisateur', 'utilisateur');
        $ajouter('parametre.consulter', 'Consulter les paramètres généraux', 'parametre');
        $ajouter('parametre.modifier', 'Modifier les paramètres généraux', 'parametre');
        $ajouter('audit.consulter', "Consulter le journal d'audit", 'audit');
        $ajouter('export.generer', 'Générer les exports Excel / PDF', 'export');
        $ajouter('tableau_bord.consulter', 'Consulter le tableau de bord', 'tableau_bord');

        // Référentiels
        foreach (self::DOMAINES_REFERENTIEL as $domaine => $libelle) {
            $ajouter("$domaine.consulter", "Consulter : $libelle", $domaine);
            $ajouter("$domaine.creer", "Créer : $libelle", $domaine);
            $ajouter("$domaine.modifier", "Modifier : $libelle", $domaine);
            $ajouter("$domaine.desactiver", "Désactiver : $libelle", $domaine);
        }
        $ajouter('referentiel.importer', 'Importer des référentiels (Excel/CSV)', 'referentiel');

        // Lot 2 — préparées dès maintenant (mouvements de stock)
        foreach (['entree' => 'Entrées de stock', 'sortie' => 'Sorties / distributions'] as $domaine => $libelle) {
            $ajouter("$domaine.consulter", "Consulter : $libelle", $domaine);
            $ajouter("$domaine.creer", "Créer : $libelle", $domaine);
            $ajouter("$domaine.modifier", "Modifier : $libelle", $domaine);
        }
        $ajouter('sortie.valider', 'Valider une opération', 'sortie');
        $ajouter('sortie.annuler', 'Annuler une opération validée', 'sortie');

        // --- Rôles ----------------------------------------------------------
        $admin = Role::updateOrCreate(
            ['code' => Role::ADMINISTRATEUR],
            ['libelle' => 'Administrateur', 'totp_obligatoire' => false,
             'description' => 'Accès total, y compris utilisateurs, paramètres et journal d\'audit.'],
        );
        $gestionnaire = Role::updateOrCreate(
            ['code' => Role::GESTIONNAIRE],
            ['libelle' => 'Gestionnaire de parc',
             'description' => 'Crée et modifie les référentiels et les mouvements, sans validation ni désactivation.'],
        );
        $valideur = Role::updateOrCreate(
            ['code' => Role::VALIDEUR],
            ['libelle' => 'Valideur',
             'description' => 'Consulte les données et valide ou annule les opérations.'],
        );
        $consultation = Role::updateOrCreate(
            ['code' => Role::CONSULTATION],
            ['libelle' => 'Consultation',
             'description' => 'Lecture seule : référentiels, états et journal d\'audit.'],
        );

        // --- Matrice des droits --------------------------------------------
        $admin->permissions()->sync(Permission::pluck('id'));

        $consulterReferentiels = array_map(
            fn ($d) => "$d.consulter",
            array_keys(self::DOMAINES_REFERENTIEL),
        );

        $codesGestionnaire = array_merge(
            ['parametre.consulter', 'export.generer', 'tableau_bord.consulter', 'referentiel.importer'],
            $consulterReferentiels,
            array_map(fn ($d) => "$d.creer", array_keys(self::DOMAINES_REFERENTIEL)),
            array_map(fn ($d) => "$d.modifier", array_keys(self::DOMAINES_REFERENTIEL)),
            ['entree.consulter', 'entree.creer', 'entree.modifier',
             'sortie.consulter', 'sortie.creer', 'sortie.modifier'],
        );

        $codesValideur = array_merge(
            ['parametre.consulter', 'export.generer', 'tableau_bord.consulter'],
            $consulterReferentiels,
            ['entree.consulter', 'sortie.consulter', 'sortie.valider', 'sortie.annuler'],
        );

        $codesConsultation = array_merge(
            ['export.generer', 'tableau_bord.consulter', 'audit.consulter'],
            $consulterReferentiels,
            ['entree.consulter', 'sortie.consulter'],
        );

        $ids = fn (array $codes) => Permission::whereIn('code', $codes)->pluck('id');
        $gestionnaire->permissions()->sync($ids($codesGestionnaire));
        $valideur->permissions()->sync($ids($codesValideur));
        $consultation->permissions()->sync($ids($codesConsultation));
    }
}
