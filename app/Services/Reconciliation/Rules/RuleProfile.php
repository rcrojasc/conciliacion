<?php
namespace App\Services\Reconciliation\Rules;
final readonly class RuleProfile {
 public function __construct(public string $id, public string $name, public int $priority, public array $weights, public array $tolerances, public float $autoApproveThreshold, public bool $enabled=true) {}
 public static function defaults(): self { return new self('default','Regla estándar',100,['amount'=>40,'currency'=>10,'date'=>10,'reference'=>20,'tax_id'=>20],['absolute'=>0.0,'percentage'=>1.0,'date_days_full'=>2,'date_days_partial'=>7],98.0,true); }
}
