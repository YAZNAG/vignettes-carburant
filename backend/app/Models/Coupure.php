<?php

namespace App\Models;

use App\Models\Concerns\Referentiel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coupure extends Model
{
    use Referentiel;

    protected $fillable = ['type_vignette_id', 'valeur', 'actif'];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'valeur' => 'decimal:2',
        ];
    }

    public function typeVignette(): BelongsTo
    {
        return $this->belongsTo(TypeVignette::class, 'type_vignette_id');
    }
}
