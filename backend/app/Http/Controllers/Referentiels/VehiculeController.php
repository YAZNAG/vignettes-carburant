<?php

namespace App\Http\Controllers\Referentiels;

use App\Models\Vehicule;
use App\Services\SimilariteService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehiculeController extends ReferentielController
{
    public const DOMAINE = 'vehicule';

    protected string $modele = Vehicule::class;

    protected array $colonnesRecherche = ['immatriculation', 'modele'];

    protected array $filtres = ['actif', 'service_id', 'site_id', 'statut', 'type_carburant', 'marque_id'];

    protected array $tris = ['id', 'immatriculation', 'statut', 'created_at'];

    protected string $triDefaut = 'immatriculation';

    protected array $relations = ['marque:id,libelle', 'service:id,libelle', 'site:id,libelle', 'conducteur:id,nom,prenom,matricule'];

    protected function libelleEntite(): string
    {
        return 'Véhicule';
    }

    protected function regles(?Model $existant): array
    {
        return [
            'immatriculation' => [
                'required', 'string', 'max:20',
                Rule::unique('vehicules', 'immatriculation')->ignore($existant?->id),
            ],
            'marque_id' => ['nullable', 'integer', Rule::exists('marques', 'id')->where('actif', true)],
            'modele' => ['nullable', 'string', 'max:50'],
            'type_vehicule' => ['required', Rule::in(Vehicule::TYPES_VEHICULE)],
            'type_carburant' => ['required', Rule::in(Vehicule::TYPES_CARBURANT)],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('actif', true)],
            'site_id' => ['nullable', 'integer', Rule::exists('sites', 'id')->where('actif', true)],
            'conducteur_id' => ['nullable', 'integer', Rule::exists('beneficiaires', 'id')->where('actif', true)],
            'plafond_mensuel' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'statut' => ['required', Rule::in(Vehicule::STATUTS)],
            'date_mise_en_service' => ['nullable', 'date'],
            'observation' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function normaliser(array $donnees): array
    {
        $donnees = parent::normaliser($donnees);

        if (isset($donnees['immatriculation'])) {
            // majuscules, sans aucun espace interne
            $donnees['immatriculation'] = mb_strtoupper(
                preg_replace('/\s+/u', '', $donnees['immatriculation']),
            );
        }

        return $donnees;
    }

    /**
     * Avertissement bloquant si une immatriculation très proche existe déjà
     * (distance de Levenshtein ≤ 2). L'utilisateur doit confirmer explicitement
     * qu'il s'agit bien d'un véhicule différent.
     */
    protected function controleSimilarite(Request $request, array $donnees, ?Model $existant): ?JsonResponse
    {
        if (! isset($donnees['immatriculation']) || $request->boolean('confirmer_similaire')) {
            return null;
        }

        $proches = app(SimilariteService::class)->valeursProches(
            Vehicule::class, 'immatriculation', $donnees['immatriculation'], $existant?->id,
        );

        if ($proches === []) {
            return null;
        }

        return response()->json([
            'message' => 'Un véhicule très proche existe déjà : '
                .collect($proches)->pluck('valeur')->join(', ')
                .". Confirmez-vous qu'il s'agit d'un véhicule différent ?",
            'confirmation_requise' => true,
            'similaires' => $proches,
        ], 409);
    }

    /** Fiche véhicule : historique des attributions (alimenté au lot 2). */
    protected function complementFiche(Model $element): array
    {
        return $element->toArray() + ['historique_attributions' => []];
    }

    protected function colonnesExport(): array
    {
        return [
            'Immatriculation' => 'immatriculation',
            'Marque' => 'marque.libelle',
            'Modèle' => 'modele',
            'Type' => 'type_vehicule',
            'Carburant' => 'type_carburant',
            'Service' => 'service.libelle',
            'Site' => 'site.libelle',
            'Conducteur habituel' => fn (Model $v) => $v->conducteur?->nom_complet,
            'Plafond mensuel (DH)' => 'plafond_mensuel',
            'Statut' => 'statut',
            'Mise en service' => fn (Model $v) => $v->date_mise_en_service?->format('d/m/Y'),
            'Actif' => 'actif',
            'Observation' => 'observation',
        ];
    }
}
