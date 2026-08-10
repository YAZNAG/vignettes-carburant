<?php

namespace App\Http\Middleware;

use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Durée de vie maximale absolue de la session : 12 heures après la connexion,
 * même en cas d'activité continue. L'inactivité (30 min paramétrables) est
 * gérée par le driver de session (voir AppServiceProvider).
 */
class LimitesDeSession
{
    public const DUREE_MAX_HEURES = 12;

    public function __construct(
        private readonly AuditService $audit,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            return $next($request);
        }

        $loginAt = $request->session()->get('auth:login_at');

        if (Auth::check() && $loginAt !== null
            && now()->timestamp - $loginAt > self::DUREE_MAX_HEURES * 3600) {

            $this->audit->enregistrer('deconnexion_duree_max', userId: Auth::id());

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'message' => 'Session expirée (durée maximale atteinte). Reconnectez-vous.',
            ], 401);
        }

        return $next($request);
    }
}
