<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    's_curve_baseline_id',
    'boq_item_id',
    'planned_start',
    'planned_finish',
])]
class SCurveItemSchedule extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'planned_start' => 'date',
            'planned_finish' => 'date',
        ];
    }

    public function baseline(): BelongsTo
    {
        return $this->belongsTo(SCurveBaseline::class, 's_curve_baseline_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(BoqItem::class, 'boq_item_id');
    }
}
