<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * Détection de quasi-doublons à la saisie (distance de Levenshtein ≤ 2).
 * Aurait évité les triplets M2214134 / M214134 / M224134 du fichier Excel.
 */
class SimilariteService
{
    public const DISTANCE_MAX = 2;

    /**
     * Retourne les valeurs existantes trop proches de la valeur saisie.
     *
     * @param class-string<Model> $modele
     * @param string $colonne colonne à comparer (ex. immatriculation)
     * @param int|null $ignorerId id à exclure (modification d'un enregistrement)
     * @return array<int, array{id: int, valeur: string}>
     */
    public function valeursProches(string $modele, string $colonne, string $valeur, ?int $ignorerId = null): array
    {
        $normalisee = $this->normaliser($valeur);

        return $modele::query()
            ->when($ignorerId, fn ($q) => $q->where('id', '!=', $ignorerId))
            ->pluck($colonne, 'id')
            ->map(fn ($existante, $id) => [
                'id' => $id,
                'valeur' => (string) $existante,
                'distance' => levenshtein($normalisee, $this->normaliser((string) $existante)),
            ])
            ->filter(fn ($c) => $c['distance'] > 0 && $c['distance'] <= self::DISTANCE_MAX)
            ->sortBy('distance')
            ->map(fn ($c) => ['id' => $c['id'], 'valeur' => $c['valeur']])
            ->values()
            ->all();
    }

    private function normaliser(string $valeur): string
    {
        return mb_strtoupper(preg_replace('/\s+/', '', $valeur));
    }
}
