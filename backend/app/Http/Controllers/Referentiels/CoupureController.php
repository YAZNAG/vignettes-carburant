<?php

namespace App\Http\Controllers\Referentiels;

use App\Models\Coupure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class CoupureController extends ReferentielController
{
    public const DOMAINE = 'coupure';

    protected string $modele = Coupure::class;

    protected array $filtres = ['actif', 'type_vignette_id'];

    protected array $tris = ['id', 'valeur'];

    protected string $triDefaut = 'valeur';

    protected array $relations = ['typeVignette:id,libelle,code'];

    protected function libelleEntite(): string
    {
        return 'Coupure';
    }

    protected function regles(?Model $existant): array
    {
        return [
            'type_vignette_id' => [
                'required', 'integer',
                Rule::exists('types_vignette', 'id')->where('actif', true),
            ],
            'valeur' => [
                'required', 'numeric', 'min:1', 'max:100000',
                Rule::unique('coupures', 'valeur')
                    ->where('type_vignette_id', request('type_vignette_id'))
                    ->ignore($existant?->id),
            ],
        ];
    }

    protected function colonnesExport(): array
    {
        return [
            'Type de vignette' => 'typeVignette.libelle',
            'Valeur faciale (DH)' => 'valeur',
            'Actif' => 'actif',
        ];
    }
}
