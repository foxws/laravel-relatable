<?php

declare(strict_types=1);

namespace Foxws\Relatable\Collections;

use Foxws\Relatable\Models\Relatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as BaseCollection;

/**
 * @template TKey of array-key
 * @template TModel of Relatable
 *
 * @extends Collection<TKey, TModel>
 */
class RelatableCollection extends Collection
{
    /**
     * Highest weight first, oldest relation first among equal weights.
     *
     * @return static<int, TModel>
     */
    public function sortByWeight(): static
    {
        return $this
            ->sortBy([
                fn (Relatable $a, Relatable $b): int => $b->weight <=> $a->weight,
                fn (Relatable $a, Relatable $b): int => $a->getKey() <=> $b->getKey(),
            ])
            ->values();
    }

    /**
     * The related models, in the collection's order. Relations whose
     * related model no longer exists are skipped.
     *
     * @return BaseCollection<int, Model>
     */
    public function models(): BaseCollection
    {
        return $this
            ->toBase()
            ->map(fn (Relatable $relatable): ?Model => $relatable->related)
            ->filter()
            ->values();
    }
}
