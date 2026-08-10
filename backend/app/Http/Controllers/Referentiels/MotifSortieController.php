<?php

namespace App\Http\Controllers\Referentiels;

use App\Models\MotifSortie;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class MotifSortieController extends ReferentielController
{
    public const DOMAINE = 'motif_sortie';

    protected string $modele = MotifSortie::class;

    protected array $colonnesRecherche = ['libelle', 'code', 'description'];

    protected array $tris = ['id', 'libelle', 'code'];

    protected string $triDefaut = 'libelle';

    protected function libelleEntite(): string
    {
        return 'Motif de sortie';
    }

    protected function regles(?Model $existant): array
    {
        return [
            'libelle' => [
                'required', 'string', 'max:100',
                Rule::unique('motifs_sortie', 'libelle')->ignore($existant?->id),
            ],
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('motifs_sortie', 'code')->ignore($existant?->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'necessite_validation' => ['required', 'boolean'],
            'soumis_plafond' => ['required', 'boolean'],
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
            'Description' => 'description',
            'Validation hiérarchique' => 'necessite_validation',
            'Soumis au plafond mensuel' => 'soumis_plafond',
            'Actif' => 'actif',
        ];
    }
}
