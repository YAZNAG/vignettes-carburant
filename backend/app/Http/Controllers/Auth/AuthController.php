<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginAttempt;
use App\Models\Parametre;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    private const MAX_ECHECS = 5;
    private const VERROUILLAGE_MINUTES = 15;
    private const MESSAGE_GENERIQUE = 'Identifiants incorrects.';

    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /**
     * Étape 1 : identifiant + mot de passe.
     * Si la 2FA est active, ouvre un défi 2FA sans établir la session authentifiée.
     */
    public function login(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'identifiant' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ], [], ['identifiant' => 'identifiant', 'password' => 'mot de passe']);

        // Limitation de débit par adresse IP (indépendante du compte)
        $cleIp = 'login-ip:'.$request->ip();
        if (RateLimiter::tooManyAttempts($cleIp, 20)) {
            $this->journaliserTentative($request, $donnees['identifiant'], null, false);

            return response()->json([
                'message' => 'Trop de tentatives depuis cette adresse. Réessayez dans quelques minutes.',
            ], 429);
        }
        RateLimiter::hit($cleIp, 300);

        $user = User::query()
            ->where('email', $donnees['identifiant'])
            ->orWhere('username', $donnees['identifiant'])
            ->first();

        // Compte verrouillé : aucune vérification supplémentaire
        if ($user && $user->estVerrouille()) {
            $this->journaliserTentative($request, $donnees['identifiant'], $user, false);
            $this->audit->enregistrer('echec_connexion_verrouille', userId: $user->id);

            return response()->json([
                'message' => 'Compte temporairement verrouillé après plusieurs échecs. Réessayez dans quelques minutes ou contactez un administrateur.',
            ], 423);
        }

        if (! $user || ! Hash::check($donnees['password'], $user->password) || ! $user->actif) {
            $this->enregistrerEchec($request, $donnees['identifiant'], $user);

            throw ValidationException::withMessages(['identifiant' => self::MESSAGE_GENERIQUE]);
        }

        // Mot de passe correct : défi 2FA éventuel avant d'authentifier
        if ($user->totpActive()) {
            $request->session()->regenerate();
            $request->session()->put('2fa:user_id', $user->id);
            $request->session()->put('2fa:expire', now()->addMinutes(5)->timestamp);

            return response()->json(['etape' => '2fa']);
        }

        return $this->etablirSession($request, $user);
    }

    /**
     * Étape 2 : vérification du code TOTP ou d'un code de secours.
     */
    public function loginDeuxFacteurs(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'code' => ['required', 'string', 'max:20'],
        ]);

        $userId = $request->session()->get('2fa:user_id');
        $expire = $request->session()->get('2fa:expire', 0);

        if (! $userId || now()->timestamp > $expire) {
            return response()->json([
                'message' => 'Défi expiré : recommencez la connexion.',
            ], 419);
        }

        $user = User::find($userId);
        if (! $user || ! $user->actif || $user->estVerrouille()) {
            $request->session()->forget(['2fa:user_id', '2fa:expire']);

            return response()->json(['message' => self::MESSAGE_GENERIQUE], 423);
        }

        if (! $this->verifierCodeDeuxFacteurs($user, $donnees['code'])) {
            $this->enregistrerEchec($request, $user->username, $user);

            throw ValidationException::withMessages(['code' => 'Code de vérification incorrect.']);
        }

        $request->session()->forget(['2fa:user_id', '2fa:expire']);

        return $this->etablirSession($request, $user);
    }

    public function logout(Request $request): JsonResponse
    {
        if ($request->user()) {
            $this->audit->enregistrer('deconnexion', userId: $request->user()->id);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Déconnexion effectuée.']);
    }

    /** Profil de l'utilisateur connecté + permissions (pour construire le menu). */
    public function me(Request $request): JsonResponse
    {
        return response()->json($this->profil($request->user()));
    }

    // ------------------------------------------------------------------ //

    private function etablirSession(Request $request, User $user): JsonResponse
    {
        // Protection contre la fixation de session
        $request->session()->regenerate();

        Auth::guard('web')->login($user);

        // Durée de vie maximale absolue de 12 h, quelle que soit l'activité
        $request->session()->put('auth:login_at', now()->timestamp);

        $user->forceFill([
            'echecs_connexion' => 0,
            'verrouille_jusqua' => null,
            'derniere_connexion_at' => now(),
        ])->saveQuietly();

        $this->journaliserTentative($request, $user->username, $user, true);
        $this->audit->enregistrer('connexion', userId: $user->id);

        return response()->json($this->profil($user));
    }

    private function profil(User $user): array
    {
        $user->load(['role.permissions', 'service', 'site']);

        return [
            'utilisateur' => [
                'id' => $user->id,
                'nom' => $user->nom,
                'prenom' => $user->prenom,
                'nom_complet' => $user->nom_complet,
                'email' => $user->email,
                'username' => $user->username,
                'role' => $user->role?->only(['id', 'code', 'libelle']),
                'service' => $user->service?->only(['id', 'libelle']),
                'site' => $user->site?->only(['id', 'libelle']),
                'doit_changer_mdp' => $user->doit_changer_mdp,
                'totp_active' => $user->totpActive(),
                'totp_requis' => $user->totpObligatoire() && ! $user->totpActive(),
                'derniere_connexion_at' => $user->derniere_connexion_at,
            ],
            'permissions' => $user->role?->permissions->pluck('code')->values() ?? [],
            'session' => [
                'inactivite_minutes' => Parametre::dureeInactiviteMinutes(),
                'duree_max_heures' => 12,
            ],
        ];
    }

    private function enregistrerEchec(Request $request, string $identifiant, ?User $user): void
    {
        $this->journaliserTentative($request, $identifiant, $user, false);

        if (! $user) {
            $this->audit->enregistrer('echec_connexion');

            return;
        }

        $echecs = $user->echecs_connexion + 1;
        $verrou = null;
        if ($echecs >= self::MAX_ECHECS) {
            $verrou = now()->addMinutes(self::VERROUILLAGE_MINUTES);
            $echecs = 0; // le compteur repart après verrouillage
        }

        $user->forceFill([
            'echecs_connexion' => $echecs,
            'verrouille_jusqua' => $verrou,
        ])->saveQuietly();

        $this->audit->enregistrer(
            $verrou ? 'verrouillage_compte' : 'echec_connexion',
            userId: $user->id,
        );
    }

    private function journaliserTentative(Request $request, string $identifiant, ?User $user, bool $succes): void
    {
        LoginAttempt::create([
            'identifiant' => mb_substr($identifiant, 0, 255),
            'user_id' => $user?->id,
            'succes' => $succes,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000) ?: null,
            'created_at' => now(),
        ]);
    }

    private function verifierCodeDeuxFacteurs(User $user, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code);

        // Code TOTP à 6 chiffres
        if (preg_match('/^\d{6}$/', $code)) {
            return (bool) app(Google2FA::class)->verifyKey($user->totp_secret, $code);
        }

        // Sinon : code de secours à usage unique
        foreach ($user->codesSecours()->whereNull('used_at')->get() as $secours) {
            if (Hash::check($code, $secours->code)) {
                $secours->forceFill(['used_at' => now()])->save();
                $this->audit->enregistrer('code_secours_utilise', userId: $user->id);

                return true;
            }
        }

        return false;
    }
}
