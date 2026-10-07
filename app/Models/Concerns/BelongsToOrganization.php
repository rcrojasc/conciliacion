<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToOrganization
{
    protected static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $builder): void {
            if (!app()->bound(TenantContext::class)) {
                return;
            }

            $context = app(TenantContext::class);

            if (!$context->hasTenant()) {
                return;
            }

            $builder->where(
                $builder->qualifyColumn('organization_id'),
                $context->id()
            );
        });

        static::creating(function ($model): void {
            if ($model->getAttribute('organization_id')) {
                return;
            }

            if (!app()->bound(TenantContext::class)) {
                return;
            }

            $context = app(TenantContext::class);

            if (!$context->hasTenant()) {
                return;
            }

            $model->setAttribute(
                'organization_id',
                $context->id()
            );
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
