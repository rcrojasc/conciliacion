<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();

            /*
             * Organización propietaria de los datos.
             * La incluimos deliberadamente para reforzar
             * el aislamiento multi-tenant.
             */
            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            /*
             * Cartola en la cual fue observado
             * el movimiento.
             */
            $table->foreignUlid('bank_statement_id')
                ->constrained('bank_statements')
                ->cascadeOnDelete();

            /*
             * Movimiento bancario único.
             *
             * No eliminamos el movimiento si se elimina
             * una cartola; solamente desaparecerá esta
             * relación por el cascade anterior.
             */
            $table->foreignUlid('bank_transaction_id')
                ->constrained('bank_transactions')
                ->cascadeOnDelete();

            /*
             * Posición exacta del movimiento dentro
             * de la cartola.
             *
             * 1, 2, 3, 4...
             */
            $table->unsignedInteger('position');

            /*
             * Número de fila física del archivo original
             * cuando esté disponible.
             *
             * Ejemplo BancoEstado:
             * encabezado fila 19
             * primer movimiento fila 20.
             */
            $table->unsignedInteger('source_row')
                ->nullable();

            /*
             * Saldo informado por el banco específicamente
             * en esta aparición del movimiento.
             *
             * Puede diferir en futuras cartolas y eso
             * permitirá detectar inconsistencias.
             */
            $table->decimal(
                'balance_after_reported',
                20,
                4
            )->nullable();

            $table->timestamps();

            /*
             * Un mismo movimiento solamente puede aparecer
             * una vez dentro de una cartola determinada.
             */
            $table->unique(
                [
                    'bank_statement_id',
                    'bank_transaction_id',
                ],
                'bst_statement_transaction_unique'
            );

            /*
             * Dentro de una cartola solamente puede existir
             * una transacción por posición.
             */
            $table->unique(
                [
                    'bank_statement_id',
                    'position',
                ],
                'bst_statement_position_unique'
            );

            /*
             * Índices para consultas frecuentes.
             */
            $table->index(
                [
                    'organization_id',
                    'bank_statement_id',
                ],
                'bst_org_statement_idx'
            );

            $table->index(
                [
                    'organization_id',
                    'bank_transaction_id',
                ],
                'bst_org_transaction_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'bank_statement_transactions'
        );
    }
};
