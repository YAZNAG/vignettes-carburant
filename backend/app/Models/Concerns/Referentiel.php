<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Comportement commun des référentiels : traçabilité, audit,
 * scope actifs (listes de saisie) — jamais de suppression physique
 * sauf si l'élément n'a jamais servi.
 */
trait Referentiel
{
    use Auditable, Traceable;

    public function scopeActifs(Builder $query): Builder
    {
        return $query->where($this->getTable().'.actif', true);
    }

    /**
     * Un élément déjà référencé ailleurs ne peut être supprimé physiquement.
     * Chaque modèle liste ses relations d'usage à contrôler.
     */
    public function estUtilise(): bool
    {
        foreach ($this->relationsUsage() as $relation) {
            if ($this->{$relation}()->exists()) {
                return true;
            }
        }

        return false;
    }

    /** @return string[] noms de relations HasMany à contrôler avant suppression */
    public function relationsUsage(): array
    {
        return [];
    }
}
