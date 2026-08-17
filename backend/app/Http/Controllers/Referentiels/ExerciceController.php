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

    protected array $relations = ['soldes.typeVignette:id,libelle,code'];

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
            // État initial : un solde par type de vignette (carburant, e-vignette, ticket…)
            'soldes' => ['nullable', 'array'],
            'soldes.*.type_vignette_id' => ['required', 'integer', Rule::exists('types_vignette', 'id')],
            'soldes.*.solde_initial' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ];
    }

    public function store(\Illuminate\Http\Request $request): JsonResponse
    {
        $donnees = $this->normaliser($request->validate($this->regles(null)));
        $soldes = $donnees['soldes'] ?? [];
        unset($donnees['soldes']);

        // Le nouvel exercice est créé ouvert s'il n'y en a pas d'autre ouvert,
        // sinon il attend la clôture du précédent (statut "cloture" impossible
        // à créer directement : il naît fermé administrativement).
        $donnees['statut'] = Exercice::ouvert()->exists() ? Exercice::CLOTURE : Exercice::OUVERT;

        $exercice = Exercice::create($donnees);
        $this->enregistrerSoldes($exercice, $soldes);

        return response()->json($exercice->load($this->relations), 201);
    }

    public function update(\Illuminate\Http\Request $request, int $id): JsonResponse
    {
        $exercice = Exercice::findOrFail($id);
        $donnees = $this->normaliser($request->validate($this->regles($exercice)));
        $soldes = $donnees['soldes'] ?? null;
        unset($donnees['soldes']);

        $exercice->update($donnees);
        if ($soldes !== null) {
            $this->enregistrerSoldes($exercice, $soldes);
        }

        return response()->json($exercice->fresh($this->relations));
    }

    /** Enregistre l'état initial et recalcule le stock initial global. */
    private function enregistrerSoldes(Exercice $exercice, array $soldes): void
    {
        foreach ($soldes as $solde) {
            $exercice->soldes()->updateOrCreate(
                ['type_vignette_id' => $solde['type_vignette_id']],
                ['solde_initial' => $solde['solde_initial']],
            );
        }

        $exercice->forceFill([
            'stock_initial' => $exercice->soldes()->sum('solde_initial'),
        ])->save();
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
            'État initial par type' => fn (Model $e) => $e->soldes
                ->map(fn ($s) => $s->typeVignette->libelle.' : '.number_format((float) $s->solde_initial, 2, ',', ' ').' DH')
                ->join(' | '),
            'Stock initial total (DH)' => 'stock_initial',
            'Statut' => fn (Model $e) => $e->statut === Exercice::OUVERT ? 'Ouvert' : 'Clôturé',
        ];
    }
}
