<?php
namespace App\Services\Reconciliation;
use App\Models\BankTransaction;use App\Services\Reconciliation\Rules\RuleResolver;use Illuminate\Support\Collection;
final class ReconciliationEngine {
 public function __construct(private ExactMatcher $matcher,private ReconciliationScorer $scorer,private RuleResolver $rules){}
 public function suggest(BankTransaction $tx): Collection { $rule=$this->rules->active();return $this->matcher->candidates($tx)->map(fn($doc)=>['document'=>$doc,'score'=>$this->scorer->score($tx,$doc,$rule),'rule'=>$rule])->sortByDesc(fn($x)=>$x['score']->score)->values(); }
}
