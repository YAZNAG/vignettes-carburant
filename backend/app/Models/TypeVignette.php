<?php

namespace App\Models;

use App\Models\Concerns\Referentiel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TypeVignette extends Model
{
    use Referentiel;

    protected $table = 'types_vignette';

    protected $fillable = ['libelle', 'code', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function coupures(): HasMany
    {
        return $this->hasMany(Coupure::class, 'type_vignette_id');
    }

    public function relationsUsage(): array
    {
        return ['coupures'];
    }

    /** Impossible de désactiver un type tant qu'une de ses coupures reste active. */
    public function aDesCoupuresActives(): bool
    {
        return $this->coupures()->where('actif', true)->exists();
    }
}
