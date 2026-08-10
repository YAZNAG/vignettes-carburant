<?php

namespace App\Models;

use App\Models\Concerns\Referentiel;
use Illuminate\Database\Eloquent\Model;

class MotifSortie extends Model
{
    use Referentiel;

    protected $table = 'motifs_sortie';

    protected $fillable = [
        'libelle', 'code', 'description',
        'necessite_validation', 'soumis_plafond', 'actif',
    ];

    protected function casts(): array
    {
        return [
            'necessite_validation' => 'boolean',
            'soumis_plafond' => 'boolean',
            'actif' => 'boolean',
        ];
    }
}
