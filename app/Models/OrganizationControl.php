<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationControl extends Model
{
    use HasUlids;

    protected $fillable = [
        'organization_id',
        'maker_checker_enabled',
        'require_checker_for_manual',
        'require_checker_for_differences',
        'checker_amount_threshold',
        'high_risk_amount_threshold',
    ];

    protected function casts(): array
    {
        return [
            'maker_checker_enabled' => 'boolean',
            'require_checker_for_manual' => 'boolean',
            'require_checker_for_differences' => 'boolean',

            'checker_amount_threshold' => 'decimal:4',
            'high_risk_amount_threshold' => 'decimal:4',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
