<?php

namespace App\Http\Controllers\Referentiels;

use App\Http\Controllers\Controller;
use App\Services\ExportExcelService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Socle commun des écrans de référentiel : liste paginée avec recherche
 * insensible aux accents et à la casse, filtres, tri, création/modification
 * normalisées, désactivation logique et export Excel.
 */
abstract class ReferentielController extends Controller
{
    /** Domaine de permission (vehicule, beneficiaire…). */
    public const DOMAINE = '';

    /** @var class-string<Model> */
    protected string $modele;

    /** Colonnes texte couvertes par la recherche plein texte. */
    protected array $colonnesRecherche = [];

    /** Champs filtrables par égalité (?service_id=… &actif=…). */
    protected array $filtres = ['actif'];

    /** Colonnes de tri autorisées. */
    protected array $tris = ['id'];

    protected string $triDefaut = 'id';

    /** Relations chargées dans les listes et fiches. */
    protected array $relations = [];

    /** Règles de validation ; $existant est null à la création. */
    abstract protected function regles(?Model $existant): array;

    /** Libellé humain de l'entité, pour les messages d'erreur. */
    abstract protected function libelleEntite(): string;

    /** Colonnes d'export : [entête => colonne ou closure(Model): scalar]. */
    abstract protected function colonnesExport(): array;

    // ------------------------------------------------------------------ //

    public function index(Request $request): JsonResponse
    {
        $parPage = min(100, max(5, (int) $request->query('par_page', 15)));

        $tri = in_array($request->query('tri'), $this->tris, true)
            ? $request->query('tri') : $this->triDefaut;
        $sens = $request->query('sens') === 'desc' ? 'desc' : 'asc';

        $resultat = $this->requeteFiltree($request)
            ->with($this->relations)
            ->orderBy($tri, $sens)
            ->orderBy('id')
            ->paginate($parPage);

        return response()->json($resultat);
    }

    public function show(int $id): JsonResponse
    {
        $element = $this->modele::with($this->relations)->findOrFail($id);

        return response()->json($this->complementFiche($element));
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $this->normaliser($request->validate($this->regles(null)));

        if ($reponse = $this->controleSimilarite($request, $donnees, null)) {
            return $reponse;
        }

        $element = $this->modele::create($donnees);

        return response()->json($element->load($this->relations), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $element = $this->modele::findOrFail($id);
        $donnees = $this->normaliser($request->validate($this->regles($element)));

        if ($reponse = $this->controleSimilarite($request, $donnees, $element)) {
            return $reponse;
        }

        $element->update($donnees);

        return response()->json($element->fresh($this->relations));
    }

    /**
     * Désactivation logique : l'élément disparaît des listes de saisie
     * mais reste visible dans l'historique. Refusée s'il n'a jamais servi
     * (dans ce cas, la suppression physique est le bon geste).
     */
    public function desactiver(int $id): JsonResponse
    {
        $element = $this->modele::findOrFail($id);

        if (! $element->actif) {
            return response()->json(['message' => 'Cet élément est déjà désactivé.'], 409);
        }

        if (! $element->estUtilise()) {
            return response()->json([
                'message' => "Cet élément n'a jamais été utilisé : supprimez-le plutôt que de le désactiver.",
                'suppression_possible' => true,
            ], 409);
        }

        $element->update(['actif' => false]);

        return response()->json(['message' => $this->libelleEntite().' désactivé(e).']);
    }

    public function reactiver(int $id): JsonResponse
    {
        $element = $this->modele::findOrFail($id);
        $element->update(['actif' => true]);

        return response()->json(['message' => $this->libelleEntite().' réactivé(e).']);
    }

    /** Suppression physique — uniquement si l'élément n'a jamais servi. */
    public function destroy(int $id): JsonResponse
    {
        $element = $this->modele::findOrFail($id);

        if ($element->estUtilise()) {
            return response()->json([
                'message' => 'Cet élément a déjà été utilisé : il ne peut être que désactivé.',
            ], 409);
        }

        $element->delete();

        return response()->json(['message' => $this->libelleEntite().' supprimé(e).']);
    }

    public function export(Request $request, ExportExcelService $excel): StreamedResponse
    {
        $colonnes = $this->colonnesExport();
        $elements = $this->requeteFiltree($request)
            ->with($this->relations)
            ->orderBy($this->triDefaut)
            ->get();

        $lignes = $elements->map(function (Model $element) use ($colonnes) {
            return array_map(
                fn ($col) => $col instanceof \Closure ? $col($element) : data_get($element, $col),
                array_values($colonnes),
            );
        });

        $nom = str_replace('_', '-', $this->modele::query()->getModel()->getTable())
            .'-'.now()->format('Y-m-d-Hi').'.xlsx';

        return $excel->telecharger($nom, array_keys($colonnes), $lignes);
    }

    // ------------------------------------------------------------------ //

    protected function requeteFiltree(Request $request): Builder
    {
        $query = $this->modele::query();

        foreach ($this->filtres as $filtre) {
            if ($request->filled($filtre)) {
                $valeur = $request->query($filtre);
                if ($filtre === 'actif') {
                    $valeur = filter_var($valeur, FILTER_VALIDATE_BOOLEAN);
                }
                $query->where($this->tableColonne($filtre), $valeur);
            }
        }

        $recherche = trim((string) $request->query('recherche', ''));
        if ($recherche !== '' && $this->colonnesRecherche !== []) {
            $query->where(function (Builder $q) use ($recherche) {
                foreach ($this->colonnesRecherche as $colonne) {
                    if (DB::connection()->getDriverName() === 'pgsql') {
                        // insensible aux accents ET à la casse
                        $q->orWhereRaw(
                            'f_unaccent(lower('.$this->tableColonne($colonne).'::text)) LIKE f_unaccent(lower(?))',
                            ['%'.$recherche.'%'],
                        );
                    } else {
                        $q->orWhere($colonne, 'like', "%$recherche%");
                    }
                }
            });
        }

        return $query;
    }

    /** Trim, espaces multiples, et mises en forme spécifiques du modèle. */
    protected function normaliser(array $donnees): array
    {
        foreach ($donnees as $cle => $valeur) {
            if (is_string($valeur)) {
                $valeur = trim(preg_replace('/\s+/u', ' ', $valeur));
                $donnees[$cle] = $valeur === '' ? null : $valeur;
            }
        }

        return $donnees;
    }

    /**
     * Contrôle de quasi-doublon avant écriture. Retourne une réponse 409
     * demandant confirmation, ou null si rien à signaler. Les enfants
     * surchargent pour activer la détection sur leur colonne clé.
     */
    protected function controleSimilarite(Request $request, array $donnees, ?Model $existant): ?JsonResponse
    {
        return null;
    }

    /** Données additionnelles de la fiche (historique, statistiques…). */
    protected function complementFiche(Model $element): array
    {
        return $element->toArray();
    }

    private function tableColonne(string $colonne): string
    {
        return (new $this->modele)->getTable().'.'.$colonne;
    }
}
