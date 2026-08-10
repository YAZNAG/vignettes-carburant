<?php

namespace App\Services;

use App\Models\PasswordHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MotDePasseService
{
    /** Nombre d'anciens mots de passe interdits à la réutilisation. */
    public const HISTORIQUE = 3;

    /**
     * Le nouveau mot de passe figure-t-il parmi les derniers utilisés
     * (mot de passe actuel inclus) ?
     */
    public function dejaUtilise(User $user, string $nouveau): bool
    {
        if (Hash::check($nouveau, $user->password)) {
            return true;
        }

        return $user->historiquesMdp()
            ->limit(self::HISTORIQUE)
            ->get()
            ->contains(fn (PasswordHistory $h) => Hash::check($nouveau, $h->password));
    }

    /**
     * Applique le nouveau mot de passe : historise l'ancien, remplace,
     * lève l'obligation de changement.
     */
    public function changer(User $user, string $nouveau): void
    {
        DB::transaction(function () use ($user, $nouveau) {
            PasswordHistory::create([
                'user_id' => $user->id,
                'password' => $user->password,
                'created_at' => now(),
            ]);

            $user->forceFill([
                'password' => $nouveau, // cast "hashed"
                'doit_changer_mdp' => false,
            ])->save();
        });
    }

    /** Invalide toutes les sessions serveur d'un utilisateur. */
    public function invaliderSessions(User $user, ?string $sessionCouranteAExclure = null): void
    {
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->when($sessionCouranteAExclure, fn ($q) => $q->where('id', '!=', $sessionCouranteAExclure))
            ->delete();
    }
}
