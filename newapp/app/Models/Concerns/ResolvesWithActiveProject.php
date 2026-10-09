<?php

namespace App\Models\Concerns;

use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Route model binding answers 404 once the owning project has been soft deleted.
 */
trait ResolvesWithActiveProject
{
    /**
     * Dot-notation relation path from this model to its project, e.g. "boq.project".
     */
    abstract protected function activeProjectRelation(): string;

    /**
     * @param  mixed  $query
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        return parent::resolveRouteBindingQuery($query, $value, $field)
            ->whereHas($this->activeProjectRelation());
    }
}
