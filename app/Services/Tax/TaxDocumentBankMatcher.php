<?php

namespace App\Services\Tax;

use App\Enums\TaxDocumentDirection;
use App\Enums\TransactionDirection;
use App\Models\BankTransaction;
use App\Models\TaxDocument;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TaxDocumentBankMatcher
{
    /**
     * Busca candidatos bancarios para un documento tributario.
     *
     * No crea conciliaciones ni modifica datos.
     *
     * @return Collection<int, array{
     *     transaction: BankTransaction,
     *     score: int,
     *     reasons: array<int, string>
     * }>
     */
    public function findCandidates(
        TaxDocument $document,
        int $daysBefore = 5,
        int $daysAfter = 45
    ): Collection {
        $expectedDirection =
            $this->expectedBankDirection($document);

        /*
         * Convertimos explícitamente a Carbon para que tanto PHP
         * como Intelephense conozcan el tipo real de las fechas.
         */
        $issueDate =
            Carbon::parse($document->issue_date);

        $dueDate =
            $document->due_date
                ? Carbon::parse($document->due_date)
                : null;

        $from =
            $issueDate
                ->copy()
                ->subDays($daysBefore)
                ->startOfDay();

        $baseDate =
            $dueDate ?? $issueDate;

        $to =
            $baseDate
                ->copy()
                ->addDays($daysAfter)
                ->endOfDay();

        $candidates =
            BankTransaction::withoutGlobalScopes()
                ->where(
                    'organization_id',
                    $document->organization_id
                )
                ->where(
                    'direction',
                    $expectedDirection->value
                )
                ->whereBetween(
                    'booking_date',
                    [
                        $from->toDateString(),
                        $to->toDateString(),
                    ]
                )
                ->get();

        return $candidates
            ->map(
                fn (BankTransaction $transaction): array =>
                    $this->score(
                        $document,
                        $transaction
                    )
            )
            ->filter(
                fn (array $candidate): bool =>
                    $candidate['score'] > 0
            )
            ->sortByDesc('score')
            ->values();
    }

    private function expectedBankDirection(
        TaxDocument $document
    ): TransactionDirection {
        return match ($document->direction) {
            TaxDocumentDirection::SALE =>
                TransactionDirection::Credit,

            TaxDocumentDirection::PURCHASE =>
                TransactionDirection::Debit,
        };
    }

    /**
     * @return array{
     *     transaction: BankTransaction,
     *     score: int,
     *     reasons: array<int, string>
     * }
     */
    private function score(
        TaxDocument $document,
        BankTransaction $transaction
    ): array {
        $score = 0;
        $reasons = [];

        $documentAmount =
            abs((float) $document->total_amount);

        $bankAmount =
            abs((float) $transaction->amount);

        /*
        |--------------------------------------------------------------------------
        | 1. Monto
        |--------------------------------------------------------------------------
        */

        if (
            abs($documentAmount - $bankAmount)
            <= 0.01
        ) {
            $score += 60;

            $reasons[] =
                'Monto exacto';
        } elseif ($documentAmount > 0) {
            $differencePercentage =
                abs($documentAmount - $bankAmount)
                / $documentAmount;

            if ($differencePercentage <= 0.01) {
                $score += 40;

                $reasons[] =
                    'Monto con diferencia menor o igual al 1%';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Fecha
        |--------------------------------------------------------------------------
        */

        $issueDate =
            Carbon::parse($document->issue_date);

        $referenceDate =
            $document->due_date
                ? Carbon::parse($document->due_date)
                : $issueDate;

        $bookingDate =
            Carbon::parse($transaction->booking_date);

        $daysDifference =
            abs(
                $referenceDate
                    ->copy()
                    ->startOfDay()
                    ->diffInDays(
                        $bookingDate
                            ->copy()
                            ->startOfDay()
                    )
            );

        if ($daysDifference <= 3) {
            $score += 20;

            $reasons[] =
                'Fecha muy cercana';
        } elseif ($daysDifference <= 10) {
            $score += 10;

            $reasons[] =
                'Fecha cercana';
        }

        /*
        |--------------------------------------------------------------------------
        | 3. RUT de contraparte
        |--------------------------------------------------------------------------
        */

        $expectedTaxId =
            $document->direction
                === TaxDocumentDirection::SALE
                    ? $document->receiver_tax_id
                    : $document->issuer_tax_id;

        if (
            $expectedTaxId
            && $transaction->counterparty_tax_id
            && $this->normalizeTaxId($expectedTaxId)
                === $this->normalizeTaxId(
                    $transaction->counterparty_tax_id
                )
        ) {
            $score += 20;

            $reasons[] =
                'RUT de contraparte coincide';
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Nombre de contraparte
        |--------------------------------------------------------------------------
        |
        | BancoEstado y otros bancos pueden entregar el nombre de la
        | contraparte dentro de la descripción aun cuando no entreguen
        | su RUT.
        |
        | Ejemplos:
        |
        | TEF A COMERCIAL JUAN PEREZ SPA
        | TEF DE TRANSPORTES DEL NORTE LTDA
        |
        */

        $expectedName =
            $document->direction
                === TaxDocumentDirection::SALE
                    ? $document->receiver_name
                    : $document->issuer_name;

        $nameMatch =
            $this->nameMatchScore(
                $expectedName,
                $transaction
            );

        if ($nameMatch >= 0.90) {
            $score += 15;

            $reasons[] =
                'Nombre de contraparte coincide';
        } elseif ($nameMatch >= 0.70) {
            $score += 10;

            $reasons[] =
                'Nombre de contraparte similar';
        }

        return [
            'transaction' => $transaction,
            'score' => min($score, 100),
            'reasons' => $reasons,
        ];
    }

    private function normalizeTaxId(
        string $taxId
    ): string {
        return strtoupper(
            preg_replace(
                '/[^0-9Kk]/',
                '',
                $taxId
            ) ?? ''
        );
    }

    private function nameMatchScore(
        ?string $expectedName,
        BankTransaction $transaction
    ): float {
        if (!$expectedName) {
            return 0.0;
        }

        $expected =
            $this->normalizeName($expectedName);

        if ($expected === '') {
            return 0.0;
        }

        /*
         * Primero usamos counterparty_name si el banco lo entregó.
         */
        if ($transaction->counterparty_name) {
            $counterparty =
                $this->normalizeName(
                    $transaction->counterparty_name
                );

            $score =
                $this->compareNames(
                    $expected,
                    $counterparty
                );

            if ($score >= 0.70) {
                return $score;
            }
        }

        /*
         * Si no existe nombre estructurado, buscamos en las
         * descripciones bancarias.
         */
        $descriptions = array_filter([
            $transaction->description_normalized,
            $transaction->description_raw,
        ]);

        $bestScore = 0.0;

        foreach ($descriptions as $description) {
            $normalizedDescription =
                $this->normalizeName(
                    (string) $description
                );

            $score =
                $this->compareNames(
                    $expected,
                    $normalizedDescription
                );

            $bestScore =
                max(
                    $bestScore,
                    $score
                );
        }

        return $bestScore;
    }

    private function normalizeName(
        string $value
    ): string {
        $value =
            mb_strtoupper(
                trim($value),
                'UTF-8'
            );

        /*
         * Eliminamos tildes para comparar nombres bancarios
         * independientemente de cómo los entregue cada banco.
         */
        $value =
            strtr(
                $value,
                [
                    'Á' => 'A',
                    'É' => 'E',
                    'Í' => 'I',
                    'Ó' => 'O',
                    'Ú' => 'U',
                    'Ü' => 'U',
                    'Ñ' => 'N',
                ]
            );

        /*
         * Eliminamos expresiones bancarias comunes.
         */
        $value =
            preg_replace(
                '/\b(TEF|TRANSFERENCIA|TRANSF|TRASPASO)\b/u',
                ' ',
                $value
            ) ?? $value;

        /*
         * Eliminamos A / DE solo cuando aparecen como palabras
         * independientes.
         */
        $value =
            preg_replace(
                '/\b(A|DE)\b/u',
                ' ',
                $value
            ) ?? $value;

        /*
         * Quitamos signos y caracteres especiales.
         */
        $value =
            preg_replace(
                '/[^A-Z0-9\s]/u',
                ' ',
                $value
            ) ?? $value;

        /*
         * Normalizamos espacios.
         */
        $value =
            preg_replace(
                '/\s+/u',
                ' ',
                $value
            ) ?? $value;

        return trim($value);
    }

    private function compareNames(
        string $expected,
        string $candidate
    ): float {
        if (
            $expected === ''
            || $candidate === ''
        ) {
            return 0.0;
        }

        /*
        * Coincidencia textual exacta.
        */
        if ($expected === $candidate) {
            return 1.0;
        }

        /*
        * Obtenemos únicamente las palabras significativas.
        *
        * significantWords() ya elimina formas societarias
        * como SPA, LTDA, SA, EIRL y SOCIEDAD.
        */
        $expectedWords =
            $this->significantWords($expected);

        $candidateWords =
            $this->significantWords($candidate);

        if (
            $expectedWords === []
            || $candidateWords === []
        ) {
            return 0.0;
        }

        /*
        * Ordenamos para que pequeñas diferencias de orden
        * no afecten una coincidencia exacta de palabras.
        */
        $expectedSorted = $expectedWords;
        $candidateSorted = $candidateWords;

        sort($expectedSorted);
        sort($candidateSorted);

        /*
        * Si contienen exactamente las mismas palabras
        * significativas, es la misma contraparte.
        *
        * Ejemplo:
        *
        * SERVICIOS TECNOLOGICOS ARICA SPA
        * SERVICIOS TECNOLOGICOS ARICA
        *
        * Ambos quedan:
        *
        * ARICA SERVICIOS TECNOLOGICOS
        */
        if ($expectedSorted === $candidateSorted) {
            return 1.0;
        }

        /*
        * Calculamos coincidencia por palabras.
        */
        $intersection =
            array_values(
                array_unique(
                    array_intersect(
                        $expectedWords,
                        $candidateWords
                    )
                )
            );

        $union =
            array_values(
                array_unique(
                    array_merge(
                        $expectedWords,
                        $candidateWords
                    )
                )
            );

        if ($union === []) {
            return 0.0;
        }

        $jaccardScore =
            count($intersection)
            / count($union);

        /*
        * Si todas las palabras significativas del nombre
        * más corto están presentes en el otro nombre,
        * calculamos también cobertura.
        */
        $smallestWordCount =
            min(
                count($expectedWords),
                count($candidateWords)
            );

        $coverageScore =
            $smallestWordCount > 0
                ? count($intersection)
                    / $smallestWordCount
                : 0.0;

        /*
        * Cobertura total con al menos dos palabras
        * significativas constituye una coincidencia fuerte.
        */
        if (
            $coverageScore === 1.0
            && count($intersection) >= 2
        ) {
            return 0.95;
        }

        return max(
            $jaccardScore,
            $coverageScore
        );
    }

    /**
     * @return array<int, string>
     */
    private function significantWords(
        string $value
    ): array {
        $ignored = [
            'SPA',
            'LTDA',
            'SA',
            'EIRL',
            'SOCIEDAD',
        ];

        $words =
            preg_split(
                '/\s+/u',
                $value,
                -1,
                PREG_SPLIT_NO_EMPTY
            ) ?: [];

        $words =
            array_filter(
                $words,
                fn (string $word): bool =>
                    mb_strlen($word) >= 3
                    && !in_array(
                        $word,
                        $ignored,
                        true
                    )
            );

        return array_values(
            array_unique($words)
        );
    }
}
