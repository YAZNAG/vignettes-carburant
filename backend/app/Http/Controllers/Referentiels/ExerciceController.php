<?php

namespace App\Http\Controllers\Referentiels;

use App\Models\Exercice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

/**
 * Exercices budgétaires. Un seul exercice ouvert à la fois (index partiel
 * PostgreSQL). La clôture avec report du disponible relève du lot 2 :
 * seule la structure est gérée ici.
 */
class ExerciceController extends ReferentielController
{
    public const DOMAINE = 'exercice';

    protected string $modele = Exercice::class;

    protected array $filtres = ['statut'];

    protected array $tris = ['id', 'annee'];

    protected string $triDefaut = 'annee';

    protected function libelleEntite(): string
    {
        return 'Exercice';
    }

    protected function regles(?Model $existant): array
    {
        return [
            'annee' => [
                'required', 'integer', 'min:2020', 'max:2100',
                Rule::unique('exercices', 'annee')->ignore($existant?->id),
            ],
            'libelle' => ['required', 'string', 'max:100'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after:date_debut'],
            'stock_initial' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function store(\Illuminate\Http\Request $request): JsonResponse
    {
        $donnees = $this->normaliser($request->validate($this->regles(null)));

        // Le nouvel exercice est créé ouvert s'il n'y en a pas d'autre ouvert,
        // sinon il attend la clôture du précédent (statut "cloture" impossible
        // à créer directement : il naît fermé administrativement).
        $donnees['statut'] = Exercice::ouvert()->exists() ? Exercice::CLOTURE : Exercice::OUVERT;

        $exercice = Exercice::create($donnees);

        return response()->json($exercice, 201);
    }

    /** Les exercices ne se désactivent pas : ils s'ouvrent ou se clôturent (lot 2). */
    public function desactiver(int $id): JsonResponse
    {
        return response()->json([
            'message' => 'Un exercice ne se désactive pas : il sera clôturé (fonction du lot 2).',
        ], 405);
    }

    public function reactiver(int $id): JsonResponse
    {
        return $this->desactiver($id);
    }

    public function destroy(int $id): JsonResponse
    {
        $exercice = Exercice::findOrFail($id);

        if ($exercice->statut === Exercice::OUVERT) {
            return response()->json([
                'message' => 'L\'exercice ouvert ne peut pas être supprimé.',
            ], 409);
        }

        $exercice->delete();

        return response()->json(['message' => 'Exercice supprimé.']);
    }

    protected function colonnesExport(): array
    {
        return [
            'Année' => 'annee',
            'Libellé' => 'libelle',
            'Début' => fn (Model $e) => $e->date_debut?->format('d/m/Y'),
            'Fin' => fn (Model $e) => $e->date_fin?->format('d/m/Y'),
            'Stock initial (DH)' => 'stock_initial',
            'Statut' => fn (Model $e) => $e->statut === Exercice::OUVERT ? 'Ouvert' : 'Clôturé',
        ];
    }
}
