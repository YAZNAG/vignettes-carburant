<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    /**
     * Écrit une entrée dans le journal d'audit (table en ajout seul).
     *
     * @param string      $action  connexion, deconnexion, echec_connexion, creation,
     *                             modification, desactivation, reactivation, suppression,
     *                             acces_refuse, changement_mdp, reinitialisation_mdp…
     * @param Model|null  $entite  entité concernée (null pour les événements d'authentification)
     */
    public function enregistrer(
        string $action,
        Model|null $entite = null,
        array|null $avant = null,
        array|null $apres = null,
        int|null $userId = null,
    ): AuditLog {
        $request = request();

        return AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'entite_type' => $entite ? class_basename($entite) : null,
            'entite_id' => $entite?->getKey(),
            'avant' => $avant,
            'apres' => $apres,
            'ip_address' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 1000) ?: null,
            'created_at' => now(),
        ]);
    }
}
