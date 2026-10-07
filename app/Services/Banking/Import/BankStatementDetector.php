<?php

namespace App\Services\Banking\Import;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class BankStatementDetector
{
    public function detect(
        string $path,
        string $extension
    ): array {
        $extension = strtolower(
            trim($extension)
        );

        if (!in_array($extension, ['xlsx', 'xls'], true)) {
            return $this->genericResult();
        }

        $spreadsheet = IOFactory::load($path);

        try {
            $sheet = $spreadsheet->getActiveSheet();

            /*
             * IMPORTANTE:
             * formatData = false.
             *
             * Al igual que en TabularReader, evitamos
             * depender del formato visual de Excel.
             */
            $rows = $sheet->rangeToArray(
                'A1:'.$sheet->getHighestDataColumn()
                    .$sheet->getHighestDataRow(),
                null,
                true,
                false,
                false
            );

            return $this->detectBancoEstadoCuentaRut(
                $rows
            );
        } finally {
            $spreadsheet->disconnectWorksheets();

            unset($spreadsheet);
        }
    }

    private function detectBancoEstadoCuentaRut(
        array $rows
    ): array {
        $isCuentaRut = false;

        $periodFrom = null;
        $periodTo = null;
        $statementYear = null;

        foreach ($rows as $row) {
            /*
             * Revisamos todas las celdas de la fila,
             * porque BancoEstado podría desplazar
             * ligeramente las etiquetas.
             */
            foreach ($row as $columnIndex => $value) {
                $normalized = $this->normalizeLabel(
                    $value
                );

                if (
                    str_contains(
                        $normalized,
                        'CARTOLA CUENTARUT'
                    )
                ) {
                    $isCuentaRut = true;
                }

                if ($normalized === 'FECHA INICIO') {
                    $periodFrom = $this->findValueAfter(
                        $row,
                        $columnIndex
                    );
                }

                if ($normalized === 'FECHA FINAL') {
                    $periodTo = $this->findValueAfter(
                        $row,
                        $columnIndex
                    );
                }
            }
        }

        if (!$isCuentaRut) {
            return $this->genericResult();
        }

        $periodFrom = $this->normalizeStatementDate(
            $periodFrom
        );

        $periodTo = $this->normalizeStatementDate(
            $periodTo
        );

        /*
         * Primero intentamos obtener el año desde
         * la fecha final y luego desde la inicial.
         */
        foreach ([$periodTo, $periodFrom] as $date) {
            if (
                $date !== null
                && preg_match(
                    '/(\d{4})$/',
                    $date,
                    $matches
                )
            ) {
                $statementYear = (int) $matches[1];

                break;
            }
        }

        return [
            'format' =>
                'bancoestado_cuentarut',

            'map' => [
                'booking_date' =>
                    'Fecha',

                'operation_number' =>
                    'N° Operación',

                'description' =>
                    'Descripción',

                'credit_amount' =>
                    'Abonos',

                'debit_amount' =>
                    'Cargos',

                'balance_after' =>
                    'Saldo',

                '_currency' =>
                    'CLP',

                '_statement_year' =>
                    $statementYear,
            ],

            'metadata' => [
                'detected_format' =>
                    'bancoestado_cuentarut',

                'period_from' =>
                    $periodFrom,

                'period_to' =>
                    $periodTo,

                'statement_year' =>
                    $statementYear,
            ],
        ];
    }

    private function findValueAfter(
        array $row,
        int|string $currentIndex
    ): mixed {
        $keys = array_keys($row);

        $position = array_search(
            $currentIndex,
            $keys,
            true
        );

        if ($position === false) {
            return null;
        }

        /*
         * Buscamos la primera celda no vacía
         * situada después de la etiqueta.
         */
        for (
            $i = $position + 1;
            $i < count($keys);
            $i++
        ) {
            $value = $row[$keys[$i]] ?? null;

            if (
                $value !== null
                && trim((string) $value) !== ''
            ) {
                return $value;
            }
        }

        return null;
    }

    private function normalizeStatementDate(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        /*
         * Excel también puede almacenar una fecha
         * como número serial.
         */
        if (
            (is_int($value) || is_float($value))
            && $value > 1000
        ) {
            try {
                return ExcelDate::excelToDateTimeObject(
                    $value
                )->format('d/m/Y');
            } catch (Throwable) {
                return null;
            }
        }

        $value = trim(
            (string) $value
        );

        if ($value === '') {
            return null;
        }

        /*
         * dd/mm/yyyy
         */
        if (
            preg_match(
                '/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/',
                $value,
                $matches
            )
        ) {
            return sprintf(
                '%02d/%02d/%04d',
                (int) $matches[1],
                (int) $matches[2],
                (int) $matches[3]
            );
        }

        /*
         * dd-mm-yyyy
         */
        if (
            preg_match(
                '/^(\d{1,2})-(\d{1,2})-(\d{4})$/',
                $value,
                $matches
            )
        ) {
            return sprintf(
                '%02d/%02d/%04d',
                (int) $matches[1],
                (int) $matches[2],
                (int) $matches[3]
            );
        }

        return $value;
    }

    private function normalizeLabel(
        mixed $value
    ): string {
        $value = mb_strtoupper(
            trim((string) $value)
        );

        $value = preg_replace(
            '/\s+/u',
            ' ',
            $value
        );

        return trim(
            $value ?? ''
        );
    }

    private function genericResult(): array
    {
        return [
            'format' =>
                'generic',

            'map' =>
                [],

            'metadata' =>
                [],
        ];
    }
}
