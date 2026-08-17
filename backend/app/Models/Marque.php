<?php

namespace App\Models;

use App\Models\Concerns\Referentiel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Marque extends Model
{
    use Referentiel;

    protected $fillable = ['libelle', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function vehicules(): HasMany
    {
        return $this->hasMany(Vehicule::class);
    }

    public function relationsUsage(): array
    {
        return ['vehicules'];
    }
}
