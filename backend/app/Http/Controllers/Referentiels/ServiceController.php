<?php

namespace App\Http\Controllers\Referentiels;

use App\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class ServiceController extends ReferentielController
{
    public const DOMAINE = 'service';

    protected string $modele = Service::class;

    protected array $colonnesRecherche = ['libelle', 'code', 'responsable'];

    protected array $tris = ['id', 'libelle', 'code'];

    protected string $triDefaut = 'libelle';

    protected function libelleEntite(): string
    {
        return 'Service';
    }

    protected function regles(?Model $existant): array
    {
        return [
            'libelle' => ['required', 'string', 'max:100'],
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('services', 'code')->ignore($existant?->id),
            ],
            'responsable' => ['nullable', 'string', 'max:100'],
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

    protected function colonnesExport(): array
    {
        return [
            'Code' => 'code',
            'Libellé' => 'libelle',
            'Responsable' => 'responsable',
            'Actif' => 'actif',
        ];
    }
}
