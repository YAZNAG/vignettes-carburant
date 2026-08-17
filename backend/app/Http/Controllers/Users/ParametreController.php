<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Parametre;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParametreController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'parametres' => Parametre::orderBy('id')->get(['id', 'cle', 'valeur', 'libelle']),
            'roles_totp' => Role::orderBy('id')->get(['id', 'code', 'libelle', 'totp_obligatoire']),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'parametres' => ['required', 'array'],
            'parametres.*.cle' => ['required', 'string', 'exists:parametres,cle'],
            'parametres.*.valeur' => ['nullable', 'string', 'max:500'],
        ]);

        foreach ($donnees['parametres'] as $ligne) {
            if ($ligne['cle'] === Parametre::DUREE_INACTIVITE) {
                $minutes = (int) ($ligne['valeur'] ?? 0);
                if ($minutes < 5 || $minutes > 480) {
                    return response()->json([
                        'message' => 'La durée d\'inactivité doit être comprise entre 5 et 480 minutes.',
                    ], 422);
                }
            }

            Parametre::where('cle', $ligne['cle'])->first()
                ?->update(['valeur' => $ligne['valeur']]);
        }

        return response()->json(['message' => 'Paramètres enregistrés.']);
    }

    /** Activation obligatoire de la 2FA par rôle. */
    public function totpObligatoire(Request $request, Role $role): JsonResponse
    {
        $donnees = $request->validate(['totp_obligatoire' => ['required', 'boolean']]);

        $role->update(['totp_obligatoire' => $donnees['totp_obligatoire']]);

        return response()->json(['message' => 'Rôle mis à jour.']);
    }
}
