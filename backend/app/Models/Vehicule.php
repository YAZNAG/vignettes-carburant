<?php

namespace App\Models;

use App\Models\Concerns\Referentiel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vehicule extends Model
{
    use Referentiel;

    public const TYPES_VEHICULE = ['Voiture', 'Utilitaire', 'Camion', '4x4', 'Autre'];
    public const TYPES_CARBURANT = ['Gasoil', 'Essence', 'Hybride', 'Électrique'];
    public const STATUTS = ['Actif', 'En réparation', 'Réformé'];

    protected $fillable = [
        'immatriculation', 'marque_id', 'modele', 'type_vehicule', 'type_carburant',
        'service_id', 'site_id', 'conducteur_id', 'plafond_mensuel', 'statut',
        'date_mise_en_service', 'observation', 'actif',
    ];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'plafond_mensuel' => 'decimal:2',
            'date_mise_en_service' => 'date',
        ];
    }

    public function marque(): BelongsTo
    {
        return $this->belongsTo(Marque::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function conducteur(): BelongsTo
    {
        return $this->belongsTo(Beneficiaire::class, 'conducteur_id');
    }
}
