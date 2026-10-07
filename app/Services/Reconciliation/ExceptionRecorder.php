<?php
namespace App\Services\Reconciliation;
use App\Models\BankTransaction;
use App\Models\ReconciliationException;
final class ExceptionRecorder { public function record(BankTransaction $tx,string $type,array $details=[]): ReconciliationException { return ReconciliationException::create(['organization_id'=>$tx->organization_id,'bank_transaction_id'=>$tx->id,'exception_type'=>$type,'details'=>$details,'status'=>'open']); } }
