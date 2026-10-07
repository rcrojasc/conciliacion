<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationRule extends Model
{
    use HasUlids;

    protected $fillable = [
        'organization_id',
        'name',
        'priority',
        'rule_type',
        'config',
        'auto_approve_threshold',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'config' => 'array',
            'auto_approve_threshold' => 'decimal:2',
            'enabled' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
