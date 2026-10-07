<?php
namespace App\Services\Reconciliation;
use App\Models\BankTransaction;use App\Models\FinancialDocument;use App\Services\Reconciliation\Rules\RuleProfile;
final class ReconciliationScorer {
 public function score(BankTransaction $tx, FinancialDocument $doc, ?RuleProfile $rule=null): ScoreResult {
  $rule??=RuleProfile::defaults();$w=$rule->weights;$t=$rule->tolerances;$c=[];$reasons=[];$score=0.0;
  $txAmount=abs((float)$tx->amount);$docAmount=(float)$doc->open_amount;$diff=abs($txAmount-$docAmount);
  $pct=$docAmount>0?($diff/$docAmount)*100:100;$withinAbsolute=$diff<=(float)($t['absolute']??0);$withinPct=$pct<=(float)($t['percentage']??0);
  $amountScore=$diff<0.0001?1.0:(($withinAbsolute||$withinPct)?.85:0.0);$c['amount']=$amountScore;$c['amount_difference']=round($diff,4);$score+=($w['amount']??0)*$amountScore;
  if($amountScore===1.0)$reasons[]='Monto exacto';elseif($amountScore>0)$reasons[]='Monto dentro de la tolerancia configurada';
  $currency=$tx->currency===$doc->currency;$c['currency']=$currency?1:0;if($currency){$score+=($w['currency']??0);$reasons[]='Moneda coincide';}
  $targetDate=$doc->due_date??$doc->issue_date;$days=$tx->booking_date&&$targetDate?abs($tx->booking_date->diffInDays($targetDate)):999;$full=(int)($t['date_days_full']??2);$partial=(int)($t['date_days_partial']??7);$dateScore=$days<=$full?1:($days<=$partial?.5:0);$c['date']=$dateScore;$c['date_difference_days']=$days;$score+=($w['date']??0)*$dateScore;if($dateScore)$reasons[]='Fecha dentro de ventana configurada';
  $haystack=mb_strtoupper(trim((string)$tx->description_normalized.' '.(string)$tx->reference.' '.(string)$tx->description_raw));$ref=$doc->folio&&str_contains($haystack,mb_strtoupper((string)$doc->folio));$c['reference']=$ref?1:0;if($ref){$score+=($w['reference']??0);$reasons[]='Folio encontrado en movimiento';}
  $taxId=$doc->counterparty?->tax_id;$taxMatch=$taxId&&$tx->counterparty_tax_id&&preg_replace('/\W/','',$taxId)===preg_replace('/\W/','',$tx->counterparty_tax_id);$c['tax_id']=$taxMatch?1:0;if($taxMatch){$score+=($w['tax_id']??0);$reasons[]='RUT de contraparte coincide';}
  return new ScoreResult(round(min(100,$score),2),$c,$reasons);
 }
}
