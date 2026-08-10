<?php

namespace App\Models;

use App\Models\Concerns\Referentiel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use Referentiel;

    protected $fillable = ['libelle', 'code', 'responsable', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function vehicules(): HasMany
    {
        return $this->hasMany(Vehicule::class);
    }

    public function beneficiaires(): HasMany
    {
        return $this->hasMany(Beneficiaire::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function relationsUsage(): array
    {
        return ['vehicules', 'beneficiaires', 'users'];
    }
}
