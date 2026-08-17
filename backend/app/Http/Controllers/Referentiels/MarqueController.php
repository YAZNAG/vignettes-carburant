<?php

namespace App\Http\Controllers\Referentiels;

use App\Models\Marque;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class MarqueController extends ReferentielController
{
    public const DOMAINE = 'marque';

    protected string $modele = Marque::class;

    protected array $colonnesRecherche = ['libelle'];

    protected array $tris = ['id', 'libelle'];

    protected string $triDefaut = 'libelle';

    protected function libelleEntite(): string
    {
        return 'Marque';
    }

    protected function regles(?Model $existant): array
    {
        return [
            'libelle' => [
                'required', 'string', 'max:50',
                Rule::unique('marques', 'libelle')->ignore($existant?->id),
            ],
        ];
    }

    protected function normaliser(array $donnees): array
    {
        $donnees = parent::normaliser($donnees);
        if (isset($donnees['libelle'])) {
            $donnees['libelle'] = mb_convert_case(mb_strtolower($donnees['libelle']), MB_CASE_TITLE, 'UTF-8');
        }

        return $donnees;
    }

    protected function colonnesExport(): array
    {
        return [
            'Marque' => 'libelle',
            'Véhicules' => fn (Model $m) => $m->vehicules()->count(),
            'Actif' => 'actif',
        ];
    }
}
