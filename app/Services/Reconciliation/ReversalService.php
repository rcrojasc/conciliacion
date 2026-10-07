<?php
namespace App\Services\Reconciliation;
use App\Enums\BankTransactionStatus;use App\Enums\DocumentStatus;use App\Enums\ReconciliationStatus;use App\Models\BankTransaction;use App\Models\FinancialDocument;use App\Models\Reconciliation;use Illuminate\Support\Facades\DB;use Illuminate\Validation\ValidationException;
final class ReversalService {
 public function reverse(Reconciliation $rec,string $reason,int|string $userId): Reconciliation { return DB::transaction(function() use($rec,$reason,$userId){
  $rec=Reconciliation::query()->with('items')->lockForUpdate()->findOrFail($rec->id);if($rec->status===ReconciliationStatus::Reversed)throw ValidationException::withMessages(['reconciliation'=>'La conciliación ya fue reversada.']);
  foreach($rec->items as $item){if($item->financial_document_id){$doc=FinancialDocument::query()->lockForUpdate()->findOrFail($item->financial_document_id);$new=(float)$doc->open_amount+(float)$item->applied_amount;$doc->update(['open_amount'=>$new,'status'=>$new>0&&$new<(float)$doc->total_amount?DocumentStatus::Partial:DocumentStatus::Open]);}if($item->bank_transaction_id){$tx=BankTransaction::query()->lockForUpdate()->findOrFail($item->bank_transaction_id);$tx->update(['status'=>BankTransactionStatus::Reversed]);}}
  $rec->update(['status'=>ReconciliationStatus::Reversed,'reversed_at'=>now(),'reversal_reason'=>$reason]);
  return Reconciliation::create(['organization_id'=>$rec->organization_id,'status'=>ReconciliationStatus::Approved,'method'=>'reversal','confidence_score'=>100,'proposed_by'=>$userId,'approved_by'=>$userId,'approved_at'=>now(),'reversal_of_id'=>$rec->id,'reversal_reason'=>$reason]);
 });}
}
