<?php

namespace App\Services;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportExcelService
{
    /**
     * Export XLSX en flux (UTF-8 natif : les accents sortent intacts).
     *
     * @param string[] $entetes libellés de colonnes
     * @param iterable<array> $lignes lignes de valeurs scalaires
     */
    public function telecharger(string $nomFichier, array $entetes, iterable $lignes): StreamedResponse
    {
        return response()->streamDownload(function () use ($entetes, $lignes) {
            $writer = new Writer;
            $writer->openToFile('php://output');

            $gras = (new Style)->setFontBold();
            $writer->addRow(Row::fromValuesWithStyle($entetes, $gras));

            foreach ($lignes as $ligne) {
                $writer->addRow(Row::fromValues(array_map(
                    fn ($v) => is_bool($v) ? ($v ? 'Oui' : 'Non') : $v,
                    $ligne,
                )));
            }

            $writer->close();
        }, $nomFichier, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
