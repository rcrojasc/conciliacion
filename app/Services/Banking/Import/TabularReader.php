<?php

namespace App\Services\Banking\Import;

use Generator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class TabularReader
{
    /**
     * Lee las filas de una cartola bancaria.
     *
     * Para XLS/XLSX se utilizan valores RAW de las celdas.
     * Esto evita que el formato visual de Excel altere
     * información financiera.
     *
     * Ejemplos:
     *
     * 264.104 -> debe conservarse como "264.104"
     * 8083883 -> debe conservarse como "8083883"
     *
     * @return Generator<int, array<string, mixed>, mixed, void>
     */
    public function rows(
        string $path,
        string $extension
    ): Generator {
        $extension = strtolower(
            trim($extension)
        );

        if ($extension === 'csv') {
            yield from $this->csvRows($path);

            return;
        }

        if (
            in_array(
                $extension,
                ['xlsx', 'xls'],
                true
            )
        ) {
            yield from $this->excelRows($path);

            return;
        }

        throw new RuntimeException(
            "Formato de archivo no soportado: {$extension}"
        );
    }

    /**
     * Lee archivos CSV.
     *
     * @return Generator<int, array<string, mixed>, mixed, void>
     */
    private function csvRows(
        string $path
    ): Generator {
        $handle = fopen(
            $path,
            'rb'
        );

        if ($handle === false) {
            throw new RuntimeException(
                'No se pudo abrir el archivo CSV.'
            );
        }

        try {
            $headers = fgetcsv($handle);

            if ($headers === false) {
                throw new RuntimeException(
                    'El archivo CSV no contiene encabezados.'
                );
            }

            $headers = array_map(
                fn ($value): string => $this->cleanHeader(
                    $value
                ),
                $headers
            );

            while (
                ($values = fgetcsv($handle)) !== false
            ) {
                if (!$this->hasData($values)) {
                    continue;
                }

                $values = array_pad(
                    $values,
                    count($headers),
                    null
                );

                $row = array_combine(
                    $headers,
                    array_slice(
                        $values,
                        0,
                        count($headers)
                    )
                );

                if ($row !== false) {
                    /** @var array<string, mixed> $row */
                    yield $row;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Lee XLS/XLSX utilizando valores RAW.
     *
     * IMPORTANTE:
     * formatData debe permanecer en FALSE.
     *
     * @return Generator<int, array<string, mixed>, mixed, void>
     */
    private function excelRows(
        string $path
    ): Generator {
        if (!is_file($path)) {
            throw new RuntimeException(
                "No existe el archivo Excel: {$path}"
            );
        }

        $spreadsheet = IOFactory::load(
            $path
        );

        try {
            $sheet = $spreadsheet->getActiveSheet();

            $headerRow = $this->findHeaderRow(
                $sheet
            );

            if ($headerRow === null) {
                throw new RuntimeException(
                    'No fue posible localizar los encabezados de movimientos bancarios.'
                );
            }

            $highestColumn = $sheet
                ->getHighestDataColumn();

            $highestRow = $sheet
                ->getHighestDataRow();

            /*
             * formatData = false
             *
             * Es crítico para la integridad financiera.
             *
             * Evita:
             *
             * "264.104" -> "264"
             * "8083883" -> "8,083,883"
             */
            $headerValues = $sheet->rangeToArray(
                "A{$headerRow}:{$highestColumn}{$headerRow}",
                null,
                true,
                false,
                false
            )[0] ?? [];

            $headers = array_map(
                fn ($value): string => $this->cleanHeader(
                    $value
                ),
                $headerValues
            );

            for (
                $rowNumber = $headerRow + 1;
                $rowNumber <= $highestRow;
                $rowNumber++
            ) {
                $values = $sheet->rangeToArray(
                    "A{$rowNumber}:{$highestColumn}{$rowNumber}",
                    null,
                    true,
                    false,
                    false
                )[0] ?? [];

                if (!$this->hasData($values)) {
                    continue;
                }

                $firstValue = $values[0] ?? null;

                /*
                 * BancoEstado agrega filas posteriores como:
                 *
                 * Subtotales
                 * notas
                 * información adicional
                 *
                 * Solo procesamos filas cuya primera columna
                 * tenga apariencia de fecha bancaria.
                 */
                if (
                    !$this->looksLikeTransactionDate(
                        $firstValue
                    )
                ) {
                    continue;
                }

                $values = array_pad(
                    $values,
                    count($headers),
                    null
                );

                $row = array_combine(
                    $headers,
                    array_slice(
                        $values,
                        0,
                        count($headers)
                    )
                );

                if ($row === false) {
                    continue;
                }

                /** @var array<string, mixed> $row */
                yield $row;
            }
        } finally {
            $spreadsheet->disconnectWorksheets();

            unset($spreadsheet);
        }
    }

    /**
     * Localiza dinámicamente la fila de encabezados.
     */
    private function findHeaderRow(
        Worksheet $sheet
    ): ?int {
        $highestColumn = $sheet
            ->getHighestDataColumn();

        $highestRow = min(
            $sheet->getHighestDataRow(),
            100
        );

        for (
            $rowNumber = 1;
            $rowNumber <= $highestRow;
            $rowNumber++
        ) {
            $values = $sheet->rangeToArray(
                "A{$rowNumber}:{$highestColumn}{$rowNumber}",
                null,
                true,
                false,
                false
            )[0] ?? [];

            $normalized = array_map(
                fn ($value): string => $this->normalizeHeader(
                    $value
                ),
                $values
            );

            $hasDate = in_array(
                'fecha',
                $normalized,
                true
            );

            $hasDescription = in_array(
                'descripcion',
                $normalized,
                true
            );

            $hasAmount =
                in_array(
                    'monto',
                    $normalized,
                    true
                )
                ||
                in_array(
                    'abonos',
                    $normalized,
                    true
                )
                ||
                in_array(
                    'cargos',
                    $normalized,
                    true
                );

            if (
                $hasDate
                && $hasDescription
                && $hasAmount
            ) {
                return $rowNumber;
            }
        }

        return null;
    }

    /**
     * Determina si el valor parece una fecha
     * correspondiente a una transacción.
     */
    private function looksLikeTransactionDate(
        mixed $value
    ): bool {
        if ($value === null) {
            return false;
        }

        /*
         * Excel también puede representar una fecha
         * mediante un número serial.
         */
        if (
            is_int($value)
            || is_float($value)
        ) {
            return $value > 1000;
        }

        $value = trim(
            (string) $value
        );

        if ($value === '') {
            return false;
        }

        /*
         * BancoEstado:
         *
         * 17/Ago
         * 1/Sep
         */
        if (
            preg_match(
                '/^\d{1,2}\/[[:alpha:]áéíóúÁÉÍÓÚ]{3,}$/u',
                $value
            ) === 1
        ) {
            return true;
        }

        /*
         * Otros formatos:
         *
         * 17/08/2026
         * 17-08-2026
         * 2026-08-17
         */
        if (
            preg_match(
                '/^\d{1,4}[\/\-]\d{1,2}[\/\-]\d{1,4}$/',
                $value
            ) === 1
        ) {
            return true;
        }

        return false;
    }

    /**
     * Comprueba si una fila contiene al menos un valor.
     *
     * @param array<int, mixed> $values
     */
    private function hasData(
        array $values
    ): bool {
        foreach ($values as $value) {
            if (
                $value !== null
                && trim((string) $value) !== ''
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Limpia un encabezado conservando su denominación.
     */
    private function cleanHeader(
        mixed $value
    ): string {
        return trim(
            preg_replace(
                '/\s+/u',
                ' ',
                (string) $value
            ) ?? ''
        );
    }

    /**
     * Normaliza un encabezado exclusivamente
     * para realizar comparaciones.
     */
    private function normalizeHeader(
        mixed $value
    ): string {
        $value = mb_strtolower(
            trim((string) $value)
        );

        $value = strtr(
            $value,
            [
                'á' => 'a',
                'é' => 'e',
                'í' => 'i',
                'ó' => 'o',
                'ú' => 'u',
                'ü' => 'u',
                'ñ' => 'n',
                '°' => '',
                'º' => '',
                "\xc2\xa0" => ' ',
            ]
        );

        return preg_replace(
            '/[^a-z0-9]+/',
            '',
            $value
        ) ?? '';
    }
}
