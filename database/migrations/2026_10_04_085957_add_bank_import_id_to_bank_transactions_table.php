<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->foreignUlid('bank_import_id')
                ->nullable()
                ->after('bank_account_id')
                ->constrained('bank_imports')
                ->nullOnDelete();

            $table->index(
                ['organization_id', 'bank_import_id'],
                'bank_transactions_org_import_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropIndex(
                'bank_transactions_org_import_idx'
            );

            $table->dropConstrainedForeignId(
                'bank_import_id'
            );
        });
    }
};
