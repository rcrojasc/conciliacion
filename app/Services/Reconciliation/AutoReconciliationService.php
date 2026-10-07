<?php
namespace App\Services\Reconciliation;
use App\Enums\BankTransactionStatus;use App\Models\BankTransaction;use App\Models\ReconciliationCandidate;use App\Models\ReconciliationRun;use App\Services\Reconciliation\Rules\RuleResolver;use App\Services\Reconciliation\Governance\GovernedReconciliationService;use Illuminate\Support\Facades\DB;
final class AutoReconciliationService {
 public function __construct(private ReconciliationEngine $engine,private GovernedReconciliationService $writer,private RuleResolver $rules){}
 public function run(int|string $userId,?string $bankAccountId=null): ReconciliationRun {
  $rule=$this->rules->active();$run=ReconciliationRun::create(['bank_account_id'=>$bankAccountId,'started_at'=>now(),'status'=>'running','metrics'=>[]]);$metrics=['processed'=>0,'auto_approved'=>0,'review'=>0,'no_match'=>0,'errors'=>0];
  $q=BankTransaction::query()->whereIn('status',[BankTransactionStatus::Imported->value,BankTransactionStatus::Normalized->value,BankTransactionStatus::Pending->value,BankTransactionStatus::CandidateFound->value]);if($bankAccountId)$q->where('bank_account_id',$bankAccountId);
  $q->orderBy('id')->chunkById(100,function($transactions)use(&$metrics,$run,$rule,$userId){foreach($transactions as $tx){$metrics['processed']++;try{$suggestions=$this->engine->suggest($tx);$best=$suggestions->first();if(!$best){$metrics['no_match']++;continue;}$candidate=ReconciliationCandidate::create(['run_id'=>$run->id,'bank_transaction_id'=>$tx->id,'candidate_type'=>'1:1','score'=>$best['score']->score,'score_breakdown'=>$best['score']->components,'reasons'=>$best['score']->reasons,'candidate_refs'=>['financial_document_ids'=>[$best['document']->id],'rule_id'=>$rule->id],'status'=>'proposed']);
    $second=$suggestions->get(1);$ambiguous=$second&&($best['score']->score-$second['score']->score)<5;if($best['score']->score>=$rule->autoApproveThreshold&&!$ambiguous){$rec=$this->writer->reconcile($tx,$best['document'],$best['score']->score,(string)$userId,'automatic',$rule->id);$candidate->update(['status'=>$rec->requires_checker?'pending_checker':'auto_approved']);if($rec->requires_checker){$metrics['review']++;}else{$metrics['auto_approved']++;}}else{$tx->update(['status'=>BankTransactionStatus::CandidateFound]);$metrics['review']++;}}
   catch(\Throwable $e){report($e);$metrics['errors']++;}}});$run->update(['finished_at'=>now(),'status'=>$metrics['errors']?'completed_with_errors':'completed','metrics'=>$metrics]);return $run->fresh();
 }
}
