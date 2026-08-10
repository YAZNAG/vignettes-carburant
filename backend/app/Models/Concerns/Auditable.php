<?php

namespace App\Models\Concerns;

use App\Services\AuditService;

/**
 * Journalise toute création / modification / désactivation dans audit_logs,
 * avec les valeurs avant et après.
 */
trait Auditable
{
    /** Attributs à ne jamais écrire dans le journal. */
    protected array $auditMasque = ['password', 'totp_secret', 'remember_token'];

    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            app(AuditService::class)->enregistrer(
                action: 'creation',
                entite: $model,
                avant: null,
                apres: $model->attributsAuditables($model->getAttributes()),
            );
        });

        static::updated(function ($model) {
            $modifies = $model->getChanges();
            unset($modifies['updated_at'], $modifies['updated_by']);
            if ($modifies === []) {
                return;
            }

            $avant = array_intersect_key($model->getOriginal(), $modifies);

            $action = 'modification';
            if (array_key_exists('actif', $modifies)) {
                $action = $modifies['actif'] ? 'reactivation' : 'desactivation';
            }

            app(AuditService::class)->enregistrer(
                action: $action,
                entite: $model,
                avant: $model->attributsAuditables($avant),
                apres: $model->attributsAuditables($modifies),
            );
        });

        static::deleted(function ($model) {
            app(AuditService::class)->enregistrer(
                action: 'suppression',
                entite: $model,
                avant: $model->attributsAuditables($model->getOriginal()),
                apres: null,
            );
        });
    }

    public function attributsAuditables(array $attributs): array
    {
        return array_diff_key($attributs, array_flip($this->auditMasque));
    }
}
