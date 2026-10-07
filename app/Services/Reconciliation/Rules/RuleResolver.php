<?php
namespace App\Services\Reconciliation\Rules;
use App\Models\ReconciliationRule;
final class RuleResolver {
 public function active(): RuleProfile {
  $rule=ReconciliationRule::query()->where('enabled',true)->orderBy('priority')->first();
  if(!$rule) return RuleProfile::defaults();
  $cfg=$rule->config ?? []; $d=RuleProfile::defaults();
  return new RuleProfile((string)$rule->id,$rule->name,(int)$rule->priority,array_replace($d->weights,(array)($cfg['weights']??[])),array_replace($d->tolerances,(array)($cfg['tolerances']??[])),(float)($rule->auto_approve_threshold ?? $d->autoApproveThreshold),true);
 }
}
