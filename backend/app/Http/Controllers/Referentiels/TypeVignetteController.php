<?php

namespace App\Http\Controllers\Referentiels;

use App\Models\TypeVignette;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class TypeVignetteController extends ReferentielController
{
    public const DOMAINE = 'type_vignette';

    protected string $modele = TypeVignette::class;

    protected array $colonnesRecherche = ['libelle', 'code'];

    protected array $tris = ['id', 'libelle', 'code'];

    protected string $triDefaut = 'libelle';

    protected array $relations = ['coupures'];

    protected function libelleEntite(): string
    {
        return 'Type de vignette';
    }

    protected function regles(?Model $existant): array
    {
        return [
            'libelle' => [
                'required', 'string', 'max:100',
                Rule::unique('types_vignette', 'libelle')->ignore($existant?->id),
            ],
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('types_vignette', 'code')->ignore($existant?->id),
            ],
        ];
    }

    protected function normaliser(array $donnees): array
    {
        $donnees = parent::normaliser($donnees);
        if (isset($donnees['code'])) {
            $donnees['code'] = mb_strtoupper($donnees['code']);
        }

        return $donnees;
    }

    /** Impossible de désactiver un type tant qu'une de ses coupures reste active. */
    public function desactiver(int $id): JsonResponse
    {
        $type = TypeVignette::findOrFail($id);

        if ($type->aDesCoupuresActives()) {
            return response()->json([
                'message' => 'Désactivez d\'abord toutes les coupures de ce type de vignette.',
            ], 409);
        }

        return parent::desactiver($id);
    }

    protected function colonnesExport(): array
    {
        return [
            'Code' => 'code',
            'Libellé' => 'libelle',
            'Coupures actives' => fn (Model $t) => $t->coupures->where('actif', true)->pluck('valeur')->join(', '),
            'Actif' => 'actif',
        ];
    }
}
