<?php
namespace App\Services\Reconciliation;
use App\Enums\DocumentStatus;
use App\Models\BankTransaction;
use App\Models\FinancialDocument;
use Illuminate\Support\Collection;
final class CombinationMatcher {
 public function oneToMany(BankTransaction $tx,int $maxDocuments=5,float $tolerance=0.0,int $windowDays=30): Collection {
  $target=abs((float)$tx->amount); if($target<=0) return collect();
  $docs=FinancialDocument::query()->whereIn('status',[DocumentStatus::Open->value,DocumentStatus::Partial->value,DocumentStatus::Overdue->value])->where('currency',$tx->currency)->where('open_amount','>',0)->whereBetween('issue_date',[$tx->booking_date->copy()->subDays($windowDays),$tx->booking_date->copy()->addDays($windowDays)])->orderBy('issue_date')->limit(40)->get();
  $solutions=[];$this->search($docs->values(),0,[],$target,$maxDocuments,$tolerance,$solutions);
  return collect($solutions)->sortBy(fn($s)=>abs($target-$s['total']))->take(10)->values();
 }
 private function search(Collection $docs,int $start,array $picked,float $target,int $max,float $tol,array &$solutions): void {
  $sum=array_sum(array_map(fn($d)=>(float)$d->open_amount,$picked));
  if(count($picked)>=2 && abs($sum-$target)<=$tol){$solutions[]=['documents'=>$picked,'total'=>$sum,'difference'=>$target-$sum,'type'=>'1:N'];return;}
  if(count($picked)>=$max || $sum>$target+$tol) return;
  for($i=$start;$i<$docs->count();$i++){ $next=$picked;$next[]=$docs[$i];$this->search($docs,$i+1,$next,$target,$max,$tol,$solutions);if(count($solutions)>=20)return; }
 }
 public function manyToOne(FinancialDocument $doc, Collection $transactions,float $tolerance=0.0,int $maxTransactions=5): Collection {
  $target=(float)$doc->open_amount;$items=$transactions->filter(fn($t)=>$t->currency===$doc->currency)->values();$solutions=[];
  $walk=function($start,$picked) use (&$walk,$items,$target,$tolerance,$maxTransactions,&$solutions){$sum=array_sum(array_map(fn($t)=>abs((float)$t->amount),$picked));if(count($picked)>=2&&abs($sum-$target)<=$tolerance){$solutions[]=['transactions'=>$picked,'total'=>$sum,'difference'=>$target-$sum,'type'=>'N:1'];return;}if(count($picked)>=$maxTransactions||$sum>$target+$tolerance)return;for($i=$start;$i<$items->count();$i++){ $n=$picked;$n[]=$items[$i];$walk($i+1,$n);if(count($solutions)>=20)return;}};$walk(0,[]);return collect($solutions)->take(10);
 }
}
