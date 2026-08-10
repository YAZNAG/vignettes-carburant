<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Role::with('permissions:id,code,libelle,domaine')
                ->orderBy('id')
                ->get(['id', 'code', 'libelle', 'description', 'totp_obligatoire']),
        );
    }
}
