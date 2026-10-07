<?php
namespace App\Services\Tenancy;
use App\Models\Organization;
use LogicException;
class TenantContext { private ?Organization $organization=null; public function set(Organization $organization): void {$this->organization=$organization;} public function clear(): void {$this->organization=null;} public function organization(): Organization { return $this->organization ?? throw new LogicException('No active organization in tenant context.'); } public function id(): string { return (string)$this->organization()->getKey(); } public function hasTenant(): bool { return $this->organization!==null; } }
