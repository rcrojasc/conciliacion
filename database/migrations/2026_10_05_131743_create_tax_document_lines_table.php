<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_document_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignUlid('tax_document_id')
                ->constrained('tax_documents')
                ->cascadeOnDelete();

            $table->unsignedInteger('line_number');

            $table->string('product_code', 100)->nullable();

            $table->text('description');

            $table->decimal('quantity', 20, 6)
                ->default(1);

            $table->string('unit', 30)->nullable();

            $table->decimal('unit_price', 20, 4)
                ->default(0);

            $table->decimal('discount_amount', 20, 4)
                ->default(0);

            $table->decimal('surcharge_amount', 20, 4)
                ->default(0);

            $table->decimal('net_subtotal', 20, 4)
                ->default(0);

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(
                [
                    'tax_document_id',
                    'line_number',
                ],
                'tax_document_lines_number_unique'
            );

            $table->index(
                [
                    'organization_id',
                    'tax_document_id',
                ],
                'tax_document_lines_org_document_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_document_lines');
    }
};
