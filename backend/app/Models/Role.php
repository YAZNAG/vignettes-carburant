<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public const ADMINISTRATEUR = 'administrateur';
    public const GESTIONNAIRE = 'gestionnaire';
    public const VALIDEUR = 'valideur';
    public const CONSULTATION = 'consultation';

    protected $fillable = ['code', 'libelle', 'description', 'totp_obligatoire', 'actif'];

    protected function casts(): array
    {
        return [
            'totp_obligatoire' => 'boolean',
            'actif' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function aPermission(string $code): bool
    {
        return $this->permissions->contains('code', $code);
    }
}
