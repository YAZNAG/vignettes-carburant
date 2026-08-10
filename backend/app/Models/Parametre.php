<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\Traceable;
use Illuminate\Database\Eloquent\Model;

class Parametre extends Model
{
    use Auditable, Traceable;

    public const NOM_ORGANISME = 'nom_organisme';
    public const LOGO_PATH = 'logo_path';
    public const DUREE_INACTIVITE = 'duree_inactivite_minutes';
    public const SEUIL_ALERTE_STOCK = 'seuil_alerte_stock';
    public const FORMAT_NUMERO_PIECE = 'format_numero_piece';

    protected $fillable = ['cle', 'valeur', 'libelle'];

    public static function valeur(string $cle, ?string $defaut = null): ?string
    {
        return static::query()->where('cle', $cle)->value('valeur') ?? $defaut;
    }

    public static function dureeInactiviteMinutes(): int
    {
        return max(5, (int) static::valeur(self::DUREE_INACTIVITE, '30'));
    }
}
