<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Renseigne automatiquement created_by / updated_by sur chaque écriture.
 */
trait Traceable
{
    public static function bootTraceable(): void
    {
        static::creating(function ($model) {
            if (Auth::check()) {
                $model->created_by ??= Auth::id();
                $model->updated_by ??= Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });
    }
}
