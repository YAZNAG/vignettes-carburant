<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\Traceable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Exercice extends Model
{
    use Auditable, Traceable;

    public const OUVERT = 'ouvert';
    public const CLOTURE = 'cloture';

    protected $fillable = [
        'annee', 'libelle', 'date_debut', 'date_fin', 'stock_initial', 'statut',
    ];

    protected function casts(): array
    {
        return [
            'annee' => 'integer',
            'date_debut' => 'date',
            'date_fin' => 'date',
            'stock_initial' => 'decimal:2',
        ];
    }

    public function scopeOuvert(Builder $query): Builder
    {
        return $query->where('statut', self::OUVERT);
    }

    public function estCloture(): bool
    {
        return $this->statut === self::CLOTURE;
    }
}
