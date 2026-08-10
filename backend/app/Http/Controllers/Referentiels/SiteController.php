<?php

namespace App\Http\Controllers\Referentiels;

use App\Models\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class SiteController extends ReferentielController
{
    public const DOMAINE = 'site';

    protected string $modele = Site::class;

    protected array $colonnesRecherche = ['libelle', 'ville', 'region'];

    protected array $tris = ['id', 'libelle', 'ville'];

    protected string $triDefaut = 'libelle';

    protected function libelleEntite(): string
    {
        return 'Site';
    }

    protected function regles(?Model $existant): array
    {
        return [
            'libelle' => [
                'required', 'string', 'max:100',
                Rule::unique('sites', 'libelle')->ignore($existant?->id),
            ],
            'ville' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function colonnesExport(): array
    {
        return [
            'Libellé' => 'libelle',
            'Ville' => 'ville',
            'Région' => 'region',
            'Actif' => 'actif',
        ];
    }
}
