<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            // purchase | sale
            $table->string('direction', 20);

            // Código SII: 33 factura electrónica, 61 nota crédito, etc.
            $table->unsignedSmallInteger('document_type');

            $table->string('folio', 50);

            $table->string('issuer_tax_id', 20);
            $table->string('issuer_name')->nullable();

            $table->string('receiver_tax_id', 20);
            $table->string('receiver_name')->nullable();

            $table->date('issue_date');

            $table->date('due_date')->nullable();

            $table->decimal('net_amount', 20, 4)->default(0);
            $table->decimal('exempt_amount', 20, 4)->default(0);
            $table->decimal('vat_amount', 20, 4)->default(0);
            $table->decimal('total_amount', 20, 4);

            $table->string('currency', 3)->default('CLP');

            /*
             * Estado tributario/documental.
             * Se mantiene separado del estado de pago.
             */
            $table->string('sii_status', 40)->nullable();

            /*
             * pending | partial | paid | overpaid | cancelled
             */
            $table->string('payment_status', 30)
                ->default('pending');

            $table->decimal('amount_paid', 20, 4)
                ->default(0);

            $table->decimal('amount_outstanding', 20, 4)
                ->default(0);

            /*
             * sii | xml | rcv | manual | provider
             */
            $table->string('source', 30);

            $table->string('external_id', 150)->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            /*
             * Un DTE se identifica esencialmente por emisor,
             * tipo de documento y folio dentro de la organización.
             */
            $table->unique(
                [
                    'organization_id',
                    'issuer_tax_id',
                    'document_type',
                    'folio',
                ],
                'tax_documents_identity_unique'
            );

            $table->index(
                [
                    'organization_id',
                    'direction',
                    'payment_status',
                ],
                'tax_documents_payment_idx'
            );

            $table->index(
                [
                    'organization_id',
                    'issue_date',
                ],
                'tax_documents_issue_date_idx'
            );

            $table->index(
                [
                    'organization_id',
                    'receiver_tax_id',
                ],
                'tax_documents_receiver_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_documents');
    }
};
