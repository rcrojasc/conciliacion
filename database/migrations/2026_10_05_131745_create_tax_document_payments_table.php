<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_document_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignUlid('tax_document_id')
                ->constrained('tax_documents')
                ->cascadeOnDelete();

            $table->foreignUlid('bank_transaction_id')
                ->constrained('bank_transactions')
                ->cascadeOnDelete();

            /*
             * Monto del movimiento bancario aplicado
             * específicamente a esta factura.
             */
            $table->decimal('amount_applied', 20, 4);

            /*
             * 0-100
             */
            $table->decimal('match_score', 5, 2)
                ->nullable();

            /*
             * exact_amount
             * rut_amount
             * combination
             * manual
             * ai_assisted
             */
            $table->string('match_method', 40);

            /*
             * proposed | approved | rejected | reversed
             */
            $table->string('status', 30)
                ->default('proposed');

            $table->timestamp('matched_at')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->foreignUlid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignUlid('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(
                [
                    'tax_document_id',
                    'bank_transaction_id',
                ],
                'tax_document_payment_unique'
            );

            $table->index(
                [
                    'organization_id',
                    'status',
                ],
                'tax_document_payments_status_idx'
            );

            $table->index(
                [
                    'organization_id',
                    'bank_transaction_id',
                ],
                'tax_document_payments_bank_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_document_payments');
    }
};
