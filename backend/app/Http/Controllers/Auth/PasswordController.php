<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\MotDePasseModifie;
use App\Rules\MotDePasseRobuste;
use App\Services\AuditService;
use App\Services\MotDePasseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    public function __construct(
        private readonly MotDePasseService $motsDePasse,
        private readonly AuditService $audit,
    ) {}

    /**
     * Changement volontaire (ou forcé à la première connexion).
     * Exige le mot de passe actuel.
     */
    public function changer(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'mot_de_passe_actuel' => ['required', 'string'],
            'nouveau_mot_de_passe' => ['required', 'string', 'confirmed', new MotDePasseRobuste],
        ], [], [
            'mot_de_passe_actuel' => 'mot de passe actuel',
            'nouveau_mot_de_passe' => 'nouveau mot de passe',
        ]);

        $user = $request->user();

        if (! Hash::check($donnees['mot_de_passe_actuel'], $user->password)) {
            throw ValidationException::withMessages([
                'mot_de_passe_actuel' => 'Le mot de passe actuel est incorrect.',
            ]);
        }

        if ($this->motsDePasse->dejaUtilise($user, $donnees['nouveau_mot_de_passe'])) {
            throw ValidationException::withMessages([
                'nouveau_mot_de_passe' => 'Ce mot de passe a déjà été utilisé récemment : choisissez-en un autre.',
            ]);
        }

        $this->motsDePasse->changer($user, $donnees['nouveau_mot_de_passe']);
        // Les autres sessions éventuelles sont invalidées, la session courante reste active
        $this->motsDePasse->invaliderSessions($user, $request->session()->getId());

        $this->audit->enregistrer('changement_mdp', userId: $user->id);
        $user->notify(new MotDePasseModifie);

        return response()->json(['message' => 'Mot de passe modifié.']);
    }

    /**
     * Mot de passe oublié : réponse identique que l'e-mail existe ou non.
     */
    public function envoyerLien(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $cleIp = 'mdp-oublie:'.$request->ip();
        if (! RateLimiter::tooManyAttempts($cleIp, 5)) {
            RateLimiter::hit($cleIp, 900);
            Password::sendResetLink($request->only('email'));
        }

        return response()->json([
            'message' => 'Si un compte correspond à cette adresse, un lien de réinitialisation vient de lui être envoyé.',
        ]);
    }

    /**
     * Réinitialisation par jeton (usage unique, 30 minutes, stocké haché).
     */
    public function reinitialiser(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', new MotDePasseRobuste],
        ]);

        $resultat = Password::reset(
            $donnees,
            function (User $user, string $password) use ($request) {
                if ($this->motsDePasse->dejaUtilise($user, $password)) {
                    throw ValidationException::withMessages([
                        'password' => 'Ce mot de passe a déjà été utilisé récemment : choisissez-en un autre.',
                    ]);
                }

                $this->motsDePasse->changer($user, $password);
                $user->forceFill([
                    'echecs_connexion' => 0,
                    'verrouille_jusqua' => null,
                ])->saveQuietly();

                // Toutes les sessions actives sont invalidées
                $this->motsDePasse->invaliderSessions($user);

                $this->audit->enregistrer('reinitialisation_mdp', userId: $user->id);
                $user->notify(new MotDePasseModifie);
            },
        );

        if ($resultat !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => 'Ce lien de réinitialisation est invalide ou a expiré.',
            ]);
        }

        return response()->json(['message' => 'Mot de passe réinitialisé : vous pouvez vous connecter.']);
    }
}
