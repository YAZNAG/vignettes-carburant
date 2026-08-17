<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\Traceable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Solde initial d'un exercice pour un type de vignette
 * (vignette carburant, e-vignette, ticket…).
 */
class ExerciceSolde extends Model
{
    use Auditable, Traceable;

    protected $table = 'exercice_soldes';

    protected $fillable = ['exercice_id', 'type_vignette_id', 'solde_initial'];

    protected function casts(): array
    {
        return ['solde_initial' => 'decimal:2'];
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function typeVignette(): BelongsTo
    {
        return $this->belongsTo(TypeVignette::class, 'type_vignette_id');
    }
}
