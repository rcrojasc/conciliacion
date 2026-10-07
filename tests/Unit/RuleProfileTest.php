<?php
namespace Tests\Unit;
use App\Services\Reconciliation\Rules\RuleProfile;use PHPUnit\Framework\TestCase;
class RuleProfileTest extends TestCase { public function test_default_weights_sum_one_hundred(): void { $this->assertSame(100,array_sum(RuleProfile::defaults()->weights)); } public function test_default_auto_threshold_is_conservative(): void { $this->assertGreaterThanOrEqual(95,RuleProfile::defaults()->autoApproveThreshold); } }
