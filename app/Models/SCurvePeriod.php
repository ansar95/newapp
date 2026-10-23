<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    's_curve_baseline_id',
    'period_start',
    'period_end',
    'planned_percent',
])]
class SCurvePeriod extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'planned_percent' => 'decimal:4',
        ];
    }

    public function baseline(): BelongsTo
    {
        return $this->belongsTo(SCurveBaseline::class, 's_curve_baseline_id');
    }
}
