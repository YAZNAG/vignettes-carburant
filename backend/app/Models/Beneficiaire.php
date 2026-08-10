<?php

namespace App\Models;

use App\Models\Concerns\Referentiel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Beneficiaire extends Model
{
    use Referentiel;

    protected $fillable = [
        'matricule', 'nom', 'prenom', 'fonction', 'service_id', 'site_id',
        'telephone', 'user_id', 'actif',
    ];

    protected $appends = ['nom_complet'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    /** Le nom complet est calculé, jamais saisi. */
    protected function nomComplet(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->prenom} {$this->nom}"));
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function compteUtilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function vehiculesConduits(): HasMany
    {
        return $this->hasMany(Vehicule::class, 'conducteur_id');
    }

    public function relationsUsage(): array
    {
        return ['vehiculesConduits'];
    }
}
