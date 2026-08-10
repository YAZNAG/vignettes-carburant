<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Rules\MotDePasseRobuste;
use App\Services\AuditService;
use App\Services\MotDePasseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(
        private readonly MotDePasseService $motsDePasse,
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $parPage = min(100, max(5, (int) $request->query('par_page', 15)));

        $query = User::query()->with(['role:id,code,libelle', 'service:id,libelle', 'site:id,libelle']);

        if ($request->filled('role_id')) {
            $query->where('role_id', $request->query('role_id'));
        }
        if ($request->filled('service_id')) {
            $query->where('service_id', $request->query('service_id'));
        }
        if ($request->filled('actif')) {
            $query->where('actif', filter_var($request->query('actif'), FILTER_VALIDATE_BOOLEAN));
        }

        $recherche = trim((string) $request->query('recherche', ''));
        if ($recherche !== '') {
            $query->where(function ($q) use ($recherche) {
                foreach (['nom', 'prenom', 'email', 'username'] as $col) {
                    $q->orWhereRaw(
                        "f_unaccent(lower($col::text)) LIKE f_unaccent(lower(?))",
                        ['%'.$recherche.'%'],
                    );
                }
            });
        }

        return response()->json(
            $query->orderBy('nom')->orderBy('prenom')->paginate($parPage),
        );
    }

    public function show(User $user): JsonResponse
    {
        return response()->json(
            $user->load(['role:id,code,libelle', 'service:id,libelle', 'site:id,libelle'])
                ->makeVisible([])
                ->toArray()
            + ['totp_active' => $user->totpActive(), 'est_verrouille' => $user->estVerrouille()],
        );
    }

    /** Dernières connexions (réussies et échouées) d'un utilisateur. */
    public function connexions(User $user): JsonResponse
    {
        return response()->json(
            $user->hasMany(\App\Models\LoginAttempt::class)
                ->latest('created_at')
                ->limit(50)
                ->get(['id', 'succes', 'ip_address', 'user_agent', 'created_at']),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $donnees = $request->validate($this->regles(null));

        $donnees['password'] = $donnees['mot_de_passe_initial'];
        unset($donnees['mot_de_passe_initial']);

        // Le nouvel utilisateur devra définir son propre mot de passe
        $donnees['doit_changer_mdp'] = true;
        $donnees['actif'] = true;

        $user = User::create($donnees);

        return response()->json($user->load('role:id,code,libelle'), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $donnees = $request->validate($this->regles($user));

        // Un administrateur ne peut pas modifier son propre rôle
        if ($user->id === $request->user()->id
            && isset($donnees['role_id'])
            && (int) $donnees['role_id'] !== $user->role_id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas modifier votre propre rôle.',
            ], 403);
        }

        // Ne jamais retirer le rôle administrateur au dernier admin actif
        if (isset($donnees['role_id'])
            && (int) $donnees['role_id'] !== $user->role_id
            && $this->estDernierAdminActif($user)) {
            return response()->json([
                'message' => 'Impossible : ce compte est le dernier administrateur actif.',
            ], 409);
        }

        unset($donnees['mot_de_passe_initial']);
        $user->update($donnees);

        return response()->json($user->fresh(['role:id,code,libelle']));
    }

    /** Désactivation : un compte n'est jamais supprimé. */
    public function desactiver(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas désactiver votre propre compte.',
            ], 403);
        }

        if ($this->estDernierAdminActif($user)) {
            return response()->json([
                'message' => 'Impossible : ce compte est le dernier administrateur actif.',
            ], 409);
        }

        $user->update(['actif' => false]);
        // Toute session en cours de ce compte est immédiatement invalidée
        $this->motsDePasse->invaliderSessions($user);

        return response()->json(['message' => 'Compte désactivé.']);
    }

    public function reactiver(User $user): JsonResponse
    {
        $user->update(['actif' => true]);

        return response()->json(['message' => 'Compte réactivé.']);
    }

    /** Réinitialisation par l'administrateur : déclenche un changement forcé. */
    public function reinitialiserMdp(Request $request, User $user): JsonResponse
    {
        $donnees = $request->validate([
            'nouveau_mot_de_passe' => ['required', 'string', new MotDePasseRobuste],
        ], [], ['nouveau_mot_de_passe' => 'nouveau mot de passe']);

        $user->forceFill([
            'password' => Hash::make($donnees['nouveau_mot_de_passe']),
            'doit_changer_mdp' => true,
            'echecs_connexion' => 0,
            'verrouille_jusqua' => null,
        ])->save();

        $this->motsDePasse->invaliderSessions($user);
        $this->audit->enregistrer('reinitialisation_mdp_admin', entite: $user);

        return response()->json([
            'message' => 'Mot de passe réinitialisé : l\'utilisateur devra le changer à sa prochaine connexion.',
        ]);
    }

    /** Déverrouillage manuel d'un compte bloqué par l'anti-force brute. */
    public function deverrouiller(User $user): JsonResponse
    {
        $user->forceFill([
            'echecs_connexion' => 0,
            'verrouille_jusqua' => null,
        ])->save();

        $this->audit->enregistrer('deverrouillage_compte', entite: $user);

        return response()->json(['message' => 'Compte déverrouillé.']);
    }

    // ------------------------------------------------------------------ //

    private function regles(?User $existant): array
    {
        $regles = [
            'nom' => ['required', 'string', 'max:50'],
            'prenom' => ['required', 'string', 'max:50'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($existant?->id),
            ],
            'username' => [
                'required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($existant?->id),
            ],
            'telephone' => ['nullable', 'string', 'max:20'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')],
            'site_id' => ['nullable', 'integer', Rule::exists('sites', 'id')],
        ];

        if ($existant === null) {
            $regles['mot_de_passe_initial'] = ['required', 'string', new MotDePasseRobuste];
        }

        return $regles;
    }

    private function estDernierAdminActif(User $user): bool
    {
        if (! $user->estAdministrateur() || ! $user->actif) {
            return false;
        }

        return User::query()
            ->where('actif', true)
            ->where('id', '!=', $user->id)
            ->whereHas('role', fn ($q) => $q->where('code', Role::ADMINISTRATEUR))
            ->doesntExist();
    }
}
