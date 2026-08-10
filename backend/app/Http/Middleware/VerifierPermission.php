<?php

namespace App\Http\Middleware;

use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrôle d'accès côté serveur : chaque route protégée déclare sa permission
 * (nomenclature domaine.action). Tout refus renvoie 403 et est journalisé.
 */
class VerifierPermission
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user || ! $user->aPermission($permission)) {
            $this->audit->enregistrer('acces_refuse', apres: [
                'permission' => $permission,
                'route' => $request->method().' '.$request->path(),
            ]);

            return response()->json([
                'message' => "Vous n'avez pas l'autorisation d'effectuer cette action.",
            ], 403);
        }

        return $next($request);
    }
}
