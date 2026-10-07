<?php

namespace Tests\Unit;

use App\Services\Banking\Import\TabularReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

class BancoEstadoXlsxReaderTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempFile = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'bancoestado_reader_test_'
            . uniqid()
            . '.xlsx';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Simula la estructura de una cartola BancoEstado.
        $sheet->setCellValue('A18', 'Detalle de Movimientos');

        $sheet->setCellValue('A19', 'Fecha');
        $sheet->setCellValue('B19', 'N° Operación');
        $sheet->setCellValue('C19', 'Descripción');
        $sheet->setCellValue('D19', 'Abonos');
        $sheet->setCellValue('E19', 'Cargos');
        $sheet->setCellValue('F19', 'Saldo');

        // Movimiento débito.
        $sheet->setCellValue('A20', '17/Ago');
        $sheet->setCellValue('B20', '8083883');
        $sheet->setCellValue(
            'C20',
            'COMISION TRANSACCION INTERNACIONAL'
        );
        $sheet->setCellValue('D20', '');
        $sheet->setCellValue('E20', 470);

        /*
         * Caso crítico:
         *
         * El valor RAW representa $264.104 CLP.
         * El formato visual de Excel puede mostrarlo como 264.
         *
         * TabularReader debe conservar el RAW.
         */
        $sheet->setCellValue('F20', '264.104');

        // Movimiento crédito.
        $sheet->setCellValue('A21', '19/Ago');
        $sheet->setCellValue('B21', '8171580');
        $sheet->setCellValue(
            'C21',
            'TEF DE NELSON NICOLAS VARELA VILLALO'
        );
        $sheet->setCellValue('D21', 50000);
        $sheet->setCellValue('E21', '');
        $sheet->setCellValue('F21', '290.145');

        /*
         * Reproducimos el formato observado en la
         * cartola real BancoEstado.
         */
        $sheet->getStyle('B20:B21')
            ->getNumberFormat()
            ->setFormatCode('#,##0;(#,##0)');

        $sheet->getStyle('F20:F21')
            ->getNumberFormat()
            ->setFormatCode('#,##0;(#,##0)');

        // Fila que NO debe convertirse en transacción.
        $sheet->setCellValue('C22', 'Subtotales $');
        $sheet->setCellValue('D22', '50.000');
        $sheet->setCellValue('E22', '470');

        $writer = new Xlsx($spreadsheet);
        $writer->save($this->tempFile);

        $spreadsheet->disconnectWorksheets();
    }

    protected function tearDown(): void
    {
        if (isset($this->tempFile) && file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }

        parent::tearDown();
    }

    public function test_reader_preserves_raw_bancoestado_values(): void
    {
        $reader = new TabularReader();

        $rows = iterator_to_array(
            $reader->rows(
                $this->tempFile,
                'xlsx'
            ),
            false
        );

        $this->assertCount(2, $rows);

        $this->assertSame(
            '17/Ago',
            $rows[0]['Fecha']
        );

        $this->assertSame(
            '8083883',
            (string) $rows[0]['N° Operación']
        );

        $this->assertSame(
            'COMISION TRANSACCION INTERNACIONAL',
            $rows[0]['Descripción']
        );

        $this->assertEquals(
            470,
            $rows[0]['Cargos']
        );

        /*
         * ASSERTION DE REGRESIÓN PRINCIPAL.
         *
         * Si alguien vuelve a activar formatData=true
         * en TabularReader, este test debe fallar.
         */
        $this->assertSame(
            '264.104',
            (string) $rows[0]['Saldo']
        );

        $this->assertSame(
            '8171580',
            (string) $rows[1]['N° Operación']
        );

        $this->assertEquals(
            50000,
            $rows[1]['Abonos']
        );

        $this->assertSame(
            '290.145',
            (string) $rows[1]['Saldo']
        );
    }

    public function test_reader_ignores_bancoestado_subtotal_rows(): void
    {
        $reader = new TabularReader();

        $rows = iterator_to_array(
            $reader->rows(
                $this->tempFile,
                'xlsx'
            ),
            false
        );

        $this->assertCount(2, $rows);

        foreach ($rows as $row) {
            $this->assertNotSame(
                'Subtotales $',
                $row['Descripción'] ?? null
            );
        }
    }
}
