<?php

namespace App\Http\Controllers\Audit;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\ExportExcelService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $parPage = min(100, max(5, (int) $request->query('par_page', 25)));

        return response()->json(
            $this->requeteFiltree($request)
                ->with('user:id,nom,prenom,username')
                ->latest('created_at')
                ->latest('id')
                ->paginate($parPage),
        );
    }

    public function export(Request $request, ExportExcelService $excel): StreamedResponse
    {
        $lignes = $this->requeteFiltree($request)
            ->with('user:id,nom,prenom,username')
            ->latest('created_at')
            ->limit(50000)
            ->get()
            ->map(fn (AuditLog $log) => [
                $log->created_at->format('d/m/Y H:i:s'),
                $log->user ? "{$log->user->prenom} {$log->user->nom} ({$log->user->username})" : '—',
                $log->action,
                $log->entite_type,
                $log->entite_id,
                $log->avant ? json_encode($log->avant, JSON_UNESCAPED_UNICODE) : '',
                $log->apres ? json_encode($log->apres, JSON_UNESCAPED_UNICODE) : '',
                $log->ip_address,
                $log->user_agent,
            ]);

        return $excel->telecharger(
            'journal-audit-'.now()->format('Y-m-d-Hi').'.xlsx',
            ['Date et heure', 'Utilisateur', 'Action', 'Entité', 'Id entité',
             'Valeurs avant', 'Valeurs après', 'Adresse IP', 'Agent utilisateur'],
            $lignes,
        );
    }

    private function requeteFiltree(Request $request): Builder
    {
        return AuditLog::query()
            ->when($request->filled('user_id'),
                fn ($q) => $q->where('user_id', $request->query('user_id')))
            ->when($request->filled('action'),
                fn ($q) => $q->where('action', $request->query('action')))
            ->when($request->filled('entite_type'),
                fn ($q) => $q->where('entite_type', $request->query('entite_type')))
            ->when($request->filled('date_debut'),
                fn ($q) => $q->where('created_at', '>=', $request->query('date_debut').' 00:00:00'))
            ->when($request->filled('date_fin'),
                fn ($q) => $q->where('created_at', '<=', $request->query('date_fin').' 23:59:59'));
    }
}
