<?php
namespace App\Services\Reconciliation;
final readonly class ScoreResult { public function __construct(public float $score, public array $criteria, public array $reasons) {} }
