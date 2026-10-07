<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_statements', function (Blueprint $table) {
            /*
            |--------------------------------------------------------------------------
            | Importación que originó la cartola
            |--------------------------------------------------------------------------
            |
            | BankImport representa el proceso de carga.
            | BankStatement representa el documento bancario resultante.
            |
            | Lo dejamos nullable porque actualmente ya existe una cartola
            | creada antes de incorporar esta relación.
            |
            */

            $table->foreignUlid('bank_import_id')
                ->nullable()
                ->after('bank_account_id')
                ->constrained('bank_imports')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Estado de procesamiento de la cartola
            |--------------------------------------------------------------------------
            */

            $table->string('status', 24)
                ->default('processed')
                ->after('currency');

            /*
            |--------------------------------------------------------------------------
            | Referencia propia del documento bancario
            |--------------------------------------------------------------------------
            |
            | Algunos bancos pueden proporcionar folio, identificador de
            | cartola o referencia externa. No todos lo hacen.
            |
            */

            $table->string('statement_reference', 120)
                ->nullable()
                ->after('status');

            /*
            |--------------------------------------------------------------------------
            | Metadata específica del banco/documento
            |--------------------------------------------------------------------------
            |
            | Aquí podremos conservar información adicional sin convertir
            | bank_statements en una tabla dependiente de BancoEstado.
            |
            */

            $table->json('metadata')
                ->nullable()
                ->after('statement_reference');

            /*
            |--------------------------------------------------------------------------
            | Índices
            |--------------------------------------------------------------------------
            */

            $table->index(
                [
                    'organization_id',
                    'bank_import_id',
                ],
                'bs_org_import_idx'
            );

            $table->index(
                [
                    'organization_id',
                    'bank_account_id',
                    'period_from',
                    'period_to',
                ],
                'bs_org_account_period_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('bank_statements', function (Blueprint $table) {
            $table->dropIndex('bs_org_account_period_idx');
            $table->dropIndex('bs_org_import_idx');

            $table->dropConstrainedForeignId('bank_import_id');

            $table->dropColumn([
                'status',
                'statement_reference',
                'metadata',
            ]);
        });
    }
};
