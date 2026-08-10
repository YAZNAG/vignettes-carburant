<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un compte marqué doit_changer_mdp ne peut rien faire d'autre que
 * définir son nouveau mot de passe (ou se déconnecter).
 */
class ForcerChangementMdp
{
    private const ROUTES_AUTORISEES = [
        'api/auth/me',
        'api/auth/logout',
        'api/auth/changer-mot-de-passe',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->doit_changer_mdp
            && ! in_array($request->path(), self::ROUTES_AUTORISEES, true)) {

            return response()->json([
                'message' => 'Vous devez définir un nouveau mot de passe avant de continuer.',
                'code' => 'MDP_A_CHANGER',
            ], 403);
        }

        return $next($request);
    }
}
