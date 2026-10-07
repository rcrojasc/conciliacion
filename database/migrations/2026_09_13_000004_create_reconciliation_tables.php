<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Reconciliation Rules
        |--------------------------------------------------------------------------
        |
        | Reglas configurables utilizadas por el motor de conciliación.
        |
        */
        Schema::create('reconciliation_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->string('name');
            $table->unsignedInteger('priority')->default(100);
            $table->string('rule_type', 40);

            $table->jsonb('config');

            $table->decimal('auto_approve_threshold', 5, 2)->nullable();
            $table->boolean('enabled')->default(true);

            $table->timestamps();

            $table->index(
                ['organization_id', 'enabled', 'priority'],
                'reconciliation_rules_org_enabled_priority_idx'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Reconciliation Runs
        |--------------------------------------------------------------------------
        |
        | Registra cada ejecución del motor de conciliación.
        |
        */
        Schema::create('reconciliation_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignUlid('bank_account_id')
                ->nullable()
                ->constrained('bank_accounts')
                ->nullOnDelete();

            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();

            $table->string('status', 24)->default('running');

            $table->jsonb('metrics')->nullable();

            $table->timestamps();

            $table->index(
                ['organization_id', 'status'],
                'reconciliation_runs_org_status_idx'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Reconciliation Candidates
        |--------------------------------------------------------------------------
        |
        | Candidatos encontrados por el motor antes de crear una conciliación.
        |
        */
        Schema::create('reconciliation_candidates', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('run_id')
                ->constrained('reconciliation_runs')
                ->cascadeOnDelete();

            $table->foreignUlid('bank_transaction_id')
                ->constrained('bank_transactions')
                ->cascadeOnDelete();

            $table->string('candidate_type', 16);

            $table->decimal('score', 5, 2);

            $table->jsonb('score_breakdown');
            $table->jsonb('reasons');
            $table->jsonb('candidate_refs');

            $table->string('status', 24)->default('proposed');

            $table->timestamps();

            $table->index(
                ['run_id', 'score'],
                'reconciliation_candidates_run_score_idx'
            );

            $table->index(
                ['bank_transaction_id', 'status'],
                'reconciliation_candidates_tx_status_idx'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Reconciliations
        |--------------------------------------------------------------------------
        |
        | Cabecera principal de una conciliación bancaria.
        |
        | IMPORTANTE:
        | reversal_of_id es una relación autorreferenciada.
        | Primero creamos la tabla y su PK y posteriormente agregamos la FK.
        |
        */
        Schema::create('reconciliations', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->string('status', 24)->default('proposed');
            $table->string('method', 24);

            $table->decimal('confidence_score', 5, 2)->nullable();

            $table->foreignUlid('proposed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignUlid('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('reversed_at')->nullable();

            /*
             * No usamos constrained() aquí.
             * La FK autorreferenciada se crea después de que PostgreSQL
             * haya creado la PK de reconciliations.
             */
            $table->ulid('reversal_of_id')->nullable();

            $table->text('reversal_reason')->nullable();

            $table->timestamps();

            $table->index(
                ['organization_id', 'status', 'created_at'],
                'reconciliations_org_status_created_idx'
            );

            $table->index(
                'reversal_of_id',
                'reconciliations_reversal_of_idx'
            );
        });

        /*
         * Ahora que reconciliations.id ya existe como PRIMARY KEY,
         * agregamos la relación autorreferenciada.
         */
        Schema::table('reconciliations', function (Blueprint $table) {
            $table->foreign('reversal_of_id')
                ->references('id')
                ->on('reconciliations')
                ->nullOnDelete();
        });

        /*
        |--------------------------------------------------------------------------
        | Reconciliation Items
        |--------------------------------------------------------------------------
        |
        | Permite representar conciliaciones:
        | 1:1
        | 1:N
        | N:1
        | N:N
        |
        */
        Schema::create('reconciliation_items', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('reconciliation_id')
                ->constrained('reconciliations')
                ->cascadeOnDelete();

            $table->foreignUlid('bank_transaction_id')
                ->nullable()
                ->constrained('bank_transactions')
                ->restrictOnDelete();

            $table->foreignUlid('financial_document_id')
                ->nullable()
                ->constrained('financial_documents')
                ->restrictOnDelete();

            $table->foreignUlid('payment_id')
                ->nullable()
                ->constrained('payments')
                ->restrictOnDelete();

            $table->decimal('applied_amount', 20, 4);
            $table->char('currency', 3);

            $table->timestamps();

            $table->index('bank_transaction_id');
            $table->index('financial_document_id');
            $table->index('payment_id');
        });

        /*
        |--------------------------------------------------------------------------
        | Reconciliation Differences
        |--------------------------------------------------------------------------
        |
        | Diferencias detectadas durante una conciliación.
        |
        */
        Schema::create('reconciliation_differences', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('reconciliation_id')
                ->constrained('reconciliations')
                ->cascadeOnDelete();

            $table->decimal('amount', 20, 4);

            $table->string('reason', 80);

            $table->string('accounting_category')->nullable();

            $table->timestamps();

            $table->index('reconciliation_id');
        });

        /*
        |--------------------------------------------------------------------------
        | Reconciliation Exceptions
        |--------------------------------------------------------------------------
        |
        | Excepciones que requieren revisión humana.
        |
        */
        Schema::create('reconciliation_exceptions', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignUlid('bank_transaction_id')
                ->nullable()
                ->constrained('bank_transactions')
                ->nullOnDelete();

            $table->string('exception_type', 64);

            $table->jsonb('details');

            $table->string('status', 24)->default('open');

            $table->foreignUlid('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(
                ['organization_id', 'status'],
                'reconciliation_exceptions_org_status_idx'
            );

            $table->index(
                ['organization_id', 'exception_type'],
                'reconciliation_exceptions_org_type_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconciliation_exceptions');
        Schema::dropIfExists('reconciliation_differences');
        Schema::dropIfExists('reconciliation_items');

        /*
         * reconciliation_candidates depende de reconciliation_runs.
         */
        Schema::dropIfExists('reconciliation_candidates');

        /*
         * Eliminamos reconciliations antes de las tablas base.
         * PostgreSQL elimina junto con la tabla su FK autorreferenciada.
         */
        Schema::dropIfExists('reconciliations');

        Schema::dropIfExists('reconciliation_runs');
        Schema::dropIfExists('reconciliation_rules');
    }
};
