<?php
namespace App\Services\Reconciliation;
use App\Models\BankTransaction;
use App\Models\FinancialDocument;
use Illuminate\Support\Collection;
final class ExactMatcher {
    public function candidates(BankTransaction $tx): Collection {
        $amount = abs((float) $tx->amount);
        return FinancialDocument::query()
            ->with('counterparty')
            ->where('currency', $tx->currency)
            ->whereIn('status', ['open','partial','overdue'])
            ->whereBetween('open_amount', [max(0, $amount * .95), $amount * 1.05])
            ->limit(100)->get();
    }
}
