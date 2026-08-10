<?php

namespace App\Http\Controllers\Referentiels;

use App\Models\Fournisseur;
use Illuminate\Database\Eloquent\Model;

class FournisseurController extends ReferentielController
{
    public const DOMAINE = 'fournisseur';

    protected string $modele = Fournisseur::class;

    protected array $colonnesRecherche = ['raison_sociale', 'identifiant_fiscal', 'ice', 'ville', 'contact'];

    protected array $tris = ['id', 'raison_sociale', 'ville'];

    protected string $triDefaut = 'raison_sociale';

    protected function libelleEntite(): string
    {
        return 'Fournisseur';
    }

    protected function regles(?Model $existant): array
    {
        return [
            'raison_sociale' => ['required', 'string', 'max:150'],
            'identifiant_fiscal' => ['nullable', 'string', 'max:30'],
            'ice' => ['nullable', 'string', 'max:30'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'ville' => ['nullable', 'string', 'max:100'],
            'telephone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'contact' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function colonnesExport(): array
    {
        return [
            'Raison sociale' => 'raison_sociale',
            'Identifiant fiscal' => 'identifiant_fiscal',
            'ICE' => 'ice',
            'Adresse' => 'adresse',
            'Ville' => 'ville',
            'Téléphone' => 'telephone',
            'E-mail' => 'email',
            'Contact' => 'contact',
            'Actif' => 'actif',
        ];
    }
}
