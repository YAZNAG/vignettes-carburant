<?php

namespace App\Http\Controllers\Referentiels;

use App\Models\Beneficiaire;
use App\Services\SimilariteService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BeneficiaireController extends ReferentielController
{
    public const DOMAINE = 'beneficiaire';

    protected string $modele = Beneficiaire::class;

    protected array $colonnesRecherche = ['matricule', 'nom', 'prenom', 'fonction'];

    protected array $filtres = ['actif', 'service_id', 'site_id'];

    protected array $tris = ['id', 'matricule', 'nom', 'created_at'];

    protected string $triDefaut = 'nom';

    protected array $relations = ['service:id,libelle', 'site:id,libelle'];

    protected function libelleEntite(): string
    {
        return 'Bénéficiaire';
    }

    protected function regles(?Model $existant): array
    {
        return [
            'matricule' => [
                'required', 'string', 'max:20',
                Rule::unique('beneficiaires', 'matricule')->ignore($existant?->id),
            ],
            'nom' => ['required', 'string', 'max:50'],
            'prenom' => ['required', 'string', 'max:50'],
            'fonction' => ['nullable', 'string', 'max:80'],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('actif', true)],
            'site_id' => ['nullable', 'integer', Rule::exists('sites', 'id')->where('actif', true)],
            'telephone' => ['nullable', 'string', 'max:20'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }

    protected function normaliser(array $donnees): array
    {
        $donnees = parent::normaliser($donnees);

        if (isset($donnees['matricule'])) {
            $donnees['matricule'] = mb_strtoupper(preg_replace('/\s+/u', '', $donnees['matricule']));
        }
        // capitalisation des noms (ELALAOUI / elalaoui → Elalaoui)
        foreach (['nom', 'prenom'] as $champ) {
            if (isset($donnees[$champ])) {
                $donnees[$champ] = mb_convert_case(mb_strtolower($donnees[$champ]), MB_CASE_TITLE, 'UTF-8');
            }
        }

        return $donnees;
    }

    protected function controleSimilarite(Request $request, array $donnees, ?Model $existant): ?JsonResponse
    {
        if (! isset($donnees['matricule']) || $request->boolean('confirmer_similaire')) {
            return null;
        }

        $proches = app(SimilariteService::class)->valeursProches(
            Beneficiaire::class, 'matricule', $donnees['matricule'], $existant?->id,
        );

        if ($proches === []) {
            return null;
        }

        return response()->json([
            'message' => 'Un bénéficiaire au matricule très proche existe déjà : '
                .collect($proches)->pluck('valeur')->join(', ')
                .". Confirmez-vous qu'il s'agit d'une personne différente ?",
            'confirmation_requise' => true,
            'similaires' => $proches,
        ], 409);
    }

    protected function colonnesExport(): array
    {
        return [
            'Matricule' => 'matricule',
            'Nom' => 'nom',
            'Prénom' => 'prenom',
            'Fonction' => 'fonction',
            'Service' => 'service.libelle',
            'Site' => 'site.libelle',
            'Téléphone' => 'telephone',
            'Actif' => 'actif',
        ];
    }
}
