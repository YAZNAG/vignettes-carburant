<?php

namespace App\Models;

use App\Models\Concerns\Referentiel;
use Illuminate\Database\Eloquent\Model;

class Fournisseur extends Model
{
    use Referentiel;

    protected $fillable = [
        'raison_sociale', 'identifiant_fiscal', 'ice', 'adresse', 'ville',
        'telephone', 'email', 'contact', 'actif',
    ];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }
}
