<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\Banking\ProcessBankImport;
use App\Models\BankAccount;
use App\Models\BankImport;
use App\Services\Banking\Import\BankStatementDetector;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BankImportController extends Controller
{
    public function store(
        Request $request,
        TenantContext $tenant,
        BankStatementDetector $detector
    ): RedirectResponse {
        $data = $request->validate(
            [
                'bank_account_id' => [
                    'required',
                    'string',
                ],

                'file' => [
                    'required',
                    'file',
                    'max:20480',
                    'mimes:csv,xlsx,xls',
                ],

                'date_column' => [
                    'nullable',
                    'string',
                    'max:120',
                ],

                'amount_column' => [
                    'nullable',
                    'string',
                    'max:120',
                ],

                'description_column' => [
                    'nullable',
                    'string',
                    'max:120',
                ],

                'reference_column' => [
                    'nullable',
                    'string',
                    'max:120',
                ],
            ],
            [
                'bank_account_id.required' =>
                    'Debe seleccionar una cuenta bancaria.',

                'file.required' =>
                    'Debe seleccionar una cartola.',

                'file.mimes' =>
                    'La cartola debe ser CSV, XLSX o XLS.',

                'file.max' =>
                    'La cartola no puede superar los 20 MB.',
            ]
        );

        $organization = $tenant->organization();

        /*
         * La cuenta debe pertenecer a la organización
         * actualmente seleccionada.
         */
        $account = BankAccount::query()
            ->whereKey($data['bank_account_id'])
            ->where(
                'organization_id',
                $organization->getKey()
            )
            ->firstOrFail();

        $file = $request->file('file');

        $extension = strtolower(
            $file->getClientOriginalExtension()
        );

        /*
         * Hash SHA-256 del archivo completo.
         *
         * Evita procesar dos veces exactamente
         * la misma cartola para la misma cuenta.
         */
        $hash = hash_file(
            'sha256',
            $file->getRealPath()
        );

        $existing = BankImport::query()
            ->where(
                'organization_id',
                $organization->getKey()
            )
            ->where(
                'bank_account_id',
                $account->getKey()
            )
            ->where(
                'file_hash',
                $hash
            )
            ->first();

        if ($existing) {
            return back()->with(
                'warning',
                'Esta cartola ya fue cargada anteriormente.'
            );
        }

        /*
         * Intentamos reconocer automáticamente
         * el formato de la cartola antes de
         * almacenar el archivo.
         */
        $detected = $detector->detect(
            $file->getRealPath(),
            $extension
        );

        $columnMap = $detected['map'];

        /*
         * Si el formato no fue reconocido,
         * utilizamos el mapeo manual ingresado
         * en el formulario.
         */
        if ($detected['format'] === 'generic') {
            if (
                empty($data['date_column'])
                || empty($data['amount_column'])
                || empty($data['description_column'])
            ) {
                return back()
                    ->withErrors([
                        'file' =>
                            'El formato de la cartola no fue reconocido automáticamente. '
                            .'Debe indicar las columnas de fecha, monto y descripción.',
                    ])
                    ->withInput();
            }

            $columnMap = [
                'booking_date' =>
                    $data['date_column'],

                'amount' =>
                    $data['amount_column'],

                'description' =>
                    $data['description_column'],
            ];

            if (!empty($data['reference_column'])) {
                $columnMap['reference'] =
                    $data['reference_column'];
            }
        }

        /*
         * Guardamos la cartola original.
         */
        $path = $file->store(
            'bank-imports/'.$organization->getKey(),
            'local'
        );

        /*
         * Conservamos metadatos del archivo y
         * de la detección automática.
         */
        $metadata = array_merge(
            [
                'original_name' =>
                    $file->getClientOriginalName(),

                'extension' =>
                    $extension,

                'detected_format' =>
                    $detected['format'],
            ],
            $detected['metadata']
        );

        /*
         * Registro maestro de la importación.
         */
        $import = BankImport::create([
            'organization_id' =>
                $organization->getKey(),

            'bank_account_id' =>
                $account->getKey(),

            'source' =>
                $extension,

            'file_hash' =>
                $hash,

            'status' =>
                'queued',

            'metadata' =>
                $metadata,
        ]);

        /*
         * El procesamiento de movimientos se
         * realiza en segundo plano.
         */
        ProcessBankImport::dispatch(
            $import->getKey(),
            Storage::disk('local')->path($path),
            $extension,
            $columnMap
        );

        return back()->with(
            'success',
            'Cartola recibida correctamente. '
            .'La importación fue enviada a procesamiento.'
        );
    }
}
