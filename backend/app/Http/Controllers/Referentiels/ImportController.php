<?php

namespace App\Http\Controllers\Referentiels;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\ExportExcelService;
use App\Services\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    public function __construct(
        private readonly ImportService $import,
        private readonly AuditService $audit,
    ) {}

    /** Modèle de fichier à télécharger (entêtes attendues + ligne d'exemple). */
    public function modele(string $type, ExportExcelService $excel): StreamedResponse
    {
        $colonnes = $this->import->colonnes($type);

        $exemple = $type === 'vehicules'
            ? ['M214134', 'Dacia', 'Duster', 'Voiture', 'Gasoil', 'DSI', 'Rabat', '2000', 'Actif', '15/03/2024', '']
            : ['B4521', 'Elalaoui', 'Karim', 'Chauffeur', 'DSI', 'Rabat', '0661000000'];

        return $excel->telecharger(
            "modele-import-$type.xlsx",
            array_values($colonnes),
            [$exemple],
        );
    }

    /** Prévisualisation : rapport ligne par ligne, aucune écriture. */
    public function previsualiser(Request $request, string $type): JsonResponse
    {
        $this->validerFichier($request);

        try {
            $rapport = $this->import->analyser($type, $request->file('fichier'));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($rapport);
    }

    /** Import définitif — atomique : soit tout passe, soit rien. */
    public function importer(Request $request, string $type): JsonResponse
    {
        $this->validerFichier($request);

        try {
            $inseres = $this->import->importer($type, $request->file('fichier'));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->audit->enregistrer('import_referentiel', apres: [
            'type' => $type,
            'lignes_inserees' => $inseres,
            'fichier' => $request->file('fichier')->getClientOriginalName(),
        ]);

        return response()->json([
            'message' => "$inseres ligne(s) importée(s).",
            'inseres' => $inseres,
        ]);
    }

    /** Validation stricte : extension, type MIME réel, taille maximale. */
    private function validerFichier(Request $request): void
    {
        $request->validate([
            'fichier' => [
                'required', 'file', 'max:5120',
                'mimes:xlsx,csv,txt',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,text/plain,application/octet-stream',
            ],
        ], [], ['fichier' => 'fichier']);
    }
}
