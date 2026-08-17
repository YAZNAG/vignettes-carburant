<?php

namespace App\Services;

use App\Models\Beneficiaire;
use App\Models\Service;
use App\Models\Site;
use App\Models\Vehicule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Import initial des référentiels depuis Excel / CSV :
 * prévisualisation, rapport d'erreurs ligne par ligne, détection des
 * doublons et quasi-doublons, insertion atomique (tout ou rien).
 */
class ImportService
{
    public function __construct(
        private readonly SimilariteService $similarite,
    ) {}

    /** true uniquement lors de l'import définitif : la prévisualisation n'écrit rien. */
    private bool $creerReferences = false;

    /** Définition des colonnes par type d'import. */
    public function colonnes(string $type): array
    {
        return match ($type) {
            'vehicules' => [
                'immatriculation' => 'Immatriculation *',
                'marque' => 'Marque',
                'modele' => 'Modèle',
                'type_vehicule' => 'Type de véhicule (Voiture, Utilitaire, Camion, 4x4, Autre)',
                'type_carburant' => 'Type de carburant (Gasoil, Essence, Hybride, Électrique)',
                'service' => 'Code service',
                'site' => 'Site',
                'plafond_mensuel' => 'Plafond mensuel (DH)',
                'statut' => 'Statut (Actif, En réparation, Réformé)',
                'date_mise_en_service' => 'Date de mise en service (JJ/MM/AAAA)',
                'observation' => 'Observation',
            ],
            'beneficiaires' => [
                'matricule' => 'Matricule *',
                'nom' => 'Nom *',
                'prenom' => 'Prénom *',
                'fonction' => 'Fonction',
                'service' => 'Code service',
                'site' => 'Site',
                'telephone' => 'Téléphone',
            ],
            default => throw new \InvalidArgumentException("Type d'import inconnu : $type"),
        };
    }

    /**
     * Analyse le fichier et retourne le rapport complet, sans rien écrire.
     *
     * @return array{lignes: array, importables: int, erreurs: int, avertissements: int}
     */
    public function analyser(string $type, UploadedFile $fichier, bool $creerReferences = false): array
    {
        $this->creerReferences = $creerReferences;
        $brutes = $this->lireFichier($fichier);
        $rapport = [];
        $vuesDansFichier = [];

        foreach ($brutes as $numero => $brute) {
            $ligne = [
                'numero' => $numero,
                'donnees' => [],
                'erreurs' => [],
                'avertissements' => [],
            ];

            $donnees = $this->normaliserLigne($type, $brute, $ligne['erreurs']);
            $ligne['donnees'] = $donnees;

            $cleUnique = $type === 'vehicules' ? 'immatriculation' : 'matricule';
            $valeurCle = $donnees[$cleUnique] ?? null;

            if ($valeurCle) {
                // doublon exact en base
                $modele = $type === 'vehicules' ? Vehicule::class : Beneficiaire::class;
                if ($modele::query()->where($cleUnique, $valeurCle)->exists()) {
                    $ligne['erreurs'][] = "« $valeurCle » existe déjà dans la base.";
                }

                // doublon exact dans le fichier
                if (isset($vuesDansFichier[mb_strtoupper($valeurCle)])) {
                    $ligne['erreurs'][] = "« $valeurCle » apparaît plusieurs fois dans le fichier (ligne "
                        .$vuesDansFichier[mb_strtoupper($valeurCle)].').';
                } else {
                    $vuesDansFichier[mb_strtoupper($valeurCle)] = $numero;
                }

                // quasi-doublon en base (Levenshtein ≤ 2)
                $proches = $this->similarite->valeursProches(
                    $type === 'vehicules' ? Vehicule::class : Beneficiaire::class,
                    $cleUnique,
                    $valeurCle,
                );
                if ($proches !== []) {
                    $ligne['avertissements'][] = 'Valeur très proche de l\'existant : '
                        .collect($proches)->pluck('valeur')->join(', ');
                }
            }

            $rapport[] = $ligne;
        }

        // quasi-doublons entre lignes du fichier
        $cles = array_keys($vuesDansFichier);
        foreach ($rapport as $i => $ligne) {
            $valeur = mb_strtoupper((string) ($ligne['donnees'][$type === 'vehicules' ? 'immatriculation' : 'matricule'] ?? ''));
            if ($valeur === '') {
                continue;
            }
            foreach ($cles as $autre) {
                if ($autre !== $valeur && levenshtein($valeur, $autre) <= SimilariteService::DISTANCE_MAX) {
                    $rapport[$i]['avertissements'][] = "Très proche de « $autre » (ligne ".$vuesDansFichier[$autre].') du même fichier.';
                }
            }
        }

        return [
            'lignes' => $rapport,
            'total' => count($rapport),
            'importables' => count(array_filter($rapport, fn ($l) => $l['erreurs'] === [])),
            'erreurs' => count(array_filter($rapport, fn ($l) => $l['erreurs'] !== [])),
            'avertissements' => count(array_filter($rapport, fn ($l) => $l['avertissements'] !== [])),
        ];
    }

    /**
     * Import atomique : la moindre erreur annule tout.
     *
     * @return int nombre de lignes insérées
     */
    public function importer(string $type, UploadedFile $fichier): int
    {
        $rapport = $this->analyser($type, $fichier, creerReferences: true);

        if ($rapport['erreurs'] > 0) {
            throw new \RuntimeException(
                'Le fichier contient '.$rapport['erreurs']." ligne(s) en erreur : l'import est annulé (aucune donnée insérée).",
            );
        }

        return DB::transaction(function () use ($type, $rapport) {
            foreach ($rapport['lignes'] as $ligne) {
                match ($type) {
                    'vehicules' => Vehicule::create($ligne['donnees']),
                    'beneficiaires' => Beneficiaire::create($ligne['donnees']),
                };
            }

            return count($rapport['lignes']);
        });
    }

    // ------------------------------------------------------------------ //

    /** @return array<int, array<string, string|null>> lignes brutes indexées par numéro (entêtes = ligne 1) */
    private function lireFichier(UploadedFile $fichier): array
    {
        $reader = strtolower($fichier->getClientOriginalExtension()) === 'csv'
            ? new CsvReader
            : new XlsxReader;

        $reader->open($fichier->getRealPath());

        $entetes = null;
        $lignes = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $index => $row) {
                $valeurs = array_map(
                    fn ($v) => $v instanceof \DateTimeInterface ? $v->format('d/m/Y') : trim((string) $v),
                    $row->toArray(),
                );

                if ($entetes === null) {
                    $entetes = array_map(fn ($e) => $this->normaliserEntete($e), $valeurs);

                    continue;
                }

                if (implode('', $valeurs) === '') {
                    continue; // ligne vide
                }

                $ligne = [];
                foreach ($entetes as $i => $cle) {
                    if ($cle !== '') {
                        $ligne[$cle] = trim((string) ($valeurs[$i] ?? ''));
                    }
                }
                $lignes[$index] = $ligne;
            }

            break; // première feuille uniquement
        }

        $reader->close();

        if ($lignes === []) {
            throw new \RuntimeException('Le fichier ne contient aucune ligne de données.');
        }

        return $lignes;
    }

    /** Rapproche l'entête du fichier de la clé interne (accents/casse/espaces ignorés). */
    private function normaliserEntete(string $entete): string
    {
        $brut = mb_strtolower(trim($entete));
        $brut = strtr($brut, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'ô' => 'o', 'î' => 'i', 'ï' => 'i', 'û' => 'u', 'ç' => 'c']);
        $brut = preg_replace('/\s*\(.*$/', '', $brut);   // retire "(DH)", "(JJ/MM/AAAA)"…
        $brut = trim(str_replace('*', '', $brut));
        $brut = preg_replace('/\s+/', '_', $brut);

        return match ($brut) {
            'immatriculation' => 'immatriculation',
            'marque' => 'marque',
            'modele' => 'modele',
            'type_de_vehicule', 'type_vehicule' => 'type_vehicule',
            'type_de_carburant', 'type_carburant', 'carburant' => 'type_carburant',
            'service', 'code_service' => 'service',
            'site' => 'site',
            'plafond_mensuel', 'plafond' => 'plafond_mensuel',
            'statut' => 'statut',
            'date_de_mise_en_service', 'date_mise_en_service', 'mise_en_service' => 'date_mise_en_service',
            'observation', 'observations' => 'observation',
            'matricule' => 'matricule',
            'nom' => 'nom',
            'prenom' => 'prenom',
            'fonction' => 'fonction',
            'telephone' => 'telephone',
            default => '',
        };
    }

    /** Normalise et valide une ligne ; alimente $erreurs. */
    private function normaliserLigne(string $type, array $brute, array &$erreurs): array
    {
        $v = fn (string $cle) => ($brute[$cle] ?? '') === '' ? null : trim(preg_replace('/\s+/u', ' ', $brute[$cle]));

        if ($type === 'vehicules') {
            $donnees = [
                'immatriculation' => $v('immatriculation') ? mb_strtoupper(preg_replace('/\s+/u', '', $v('immatriculation'))) : null,
                // la marque est un référentiel : créée à la volée lors de
                // l'import définitif (jamais pendant la prévisualisation)
                'marque_id' => $this->resoudreMarque($v('marque')),
                'modele' => $v('modele'),
                'type_vehicule' => $v('type_vehicule') ?? 'Voiture',
                'type_carburant' => $v('type_carburant') ?? 'Gasoil',
                'plafond_mensuel' => $v('plafond_mensuel') !== null ? (float) str_replace([' ', ','], ['', '.'], $v('plafond_mensuel')) : null,
                'statut' => $v('statut') ?? 'Actif',
                'observation' => $v('observation'),
                'actif' => true,
            ];

            if (! $donnees['immatriculation']) {
                $erreurs[] = 'Immatriculation obligatoire.';
            }
            if (! in_array($donnees['type_vehicule'], Vehicule::TYPES_VEHICULE, true)) {
                $erreurs[] = 'Type de véhicule inconnu : '.$donnees['type_vehicule'];
            }
            if (! in_array($donnees['type_carburant'], Vehicule::TYPES_CARBURANT, true)) {
                $erreurs[] = 'Type de carburant inconnu : '.$donnees['type_carburant'];
            }
            if (! in_array($donnees['statut'], Vehicule::STATUTS, true)) {
                $erreurs[] = 'Statut inconnu : '.$donnees['statut'];
            }

            if ($date = $v('date_mise_en_service')) {
                try {
                    $donnees['date_mise_en_service'] = \Carbon\Carbon::createFromFormat('d/m/Y', $date)->format('Y-m-d');
                } catch (\Throwable) {
                    $erreurs[] = "Date de mise en service invalide : $date (format attendu JJ/MM/AAAA).";
                }
            }

            $donnees['service_id'] = $this->resoudreReference(Service::class, ['code', 'libelle'], $v('service'), 'Service', $erreurs);
            $donnees['site_id'] = $this->resoudreReference(Site::class, ['libelle'], $v('site'), 'Site', $erreurs);

            return $donnees;
        }

        // bénéficiaires
        $capitaliser = fn (?string $s) => $s === null ? null : mb_convert_case(mb_strtolower($s), MB_CASE_TITLE, 'UTF-8');

        $donnees = [
            'matricule' => $v('matricule') ? mb_strtoupper(preg_replace('/\s+/u', '', $v('matricule'))) : null,
            'nom' => $capitaliser($v('nom')),
            'prenom' => $capitaliser($v('prenom')),
            'fonction' => $v('fonction'),
            'telephone' => $v('telephone'),
            'actif' => true,
        ];

        if (! $donnees['matricule']) {
            $erreurs[] = 'Matricule obligatoire.';
        }
        if (! $donnees['nom']) {
            $erreurs[] = 'Nom obligatoire.';
        }
        if (! $donnees['prenom']) {
            $erreurs[] = 'Prénom obligatoire.';
        }

        $donnees['service_id'] = $this->resoudreReference(Service::class, ['code', 'libelle'], $v('service'), 'Service', $erreurs);
        $donnees['site_id'] = $this->resoudreReference(Site::class, ['libelle'], $v('site'), 'Site', $erreurs);

        return $donnees;
    }

    private function resoudreMarque(?string $libelle): ?int
    {
        if ($libelle === null) {
            return null;
        }

        $normalise = mb_convert_case(mb_strtolower($libelle), MB_CASE_TITLE, 'UTF-8');

        return $this->creerReferences
            ? \App\Models\Marque::firstOrCreate(['libelle' => $normalise])->id
            : \App\Models\Marque::where('libelle', $normalise)->value('id');
    }

    private function resoudreReference(string $modele, array $colonnes, ?string $valeur, string $libelle, array &$erreurs): ?int
    {
        if ($valeur === null) {
            return null;
        }

        $query = $modele::query();
        foreach ($colonnes as $colonne) {
            $query->orWhereRaw(
                "f_unaccent(lower($colonne::text)) = f_unaccent(lower(?))",
                [$valeur],
            );
        }

        $id = $query->value('id');
        if ($id === null) {
            $erreurs[] = "$libelle inconnu : « $valeur » (créez-le d'abord dans le référentiel).";
        }

        return $id;
    }
}
