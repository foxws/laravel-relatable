<?php

declare(strict_types=1);

namespace Foxws\Relatable\QueryBuilders;

use Foxws\Relatable\Models\Relatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

/**
 * @template TModel of Relatable
 *
 * @extends Builder<TModel>
 */
class RelatableQueryBuilder extends Builder
{
    protected const string WEIGHT_EXPRESSION = '(score * boost)';

    /**
     * Add the relation's weight (score × boost) as a `weight` column.
     */
    public function withWeight(): static
    {
        if ($this->getQuery()->columns === null) {
            $this->select($this->qualifyColumn('*'));
        }

        $this->selectRaw(self::WEIGHT_EXPRESSION.' as weight');

        return $this;
    }

    public function orderByWeight(string $direction = 'desc'): static
    {
        $direction = strtolower($direction);

        if (! in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('Order direction must be "asc" or "desc".');
        }

        $this
            ->orderByRaw(self::WEIGHT_EXPRESSION.' '.($direction === 'asc' ? 'asc' : 'desc'))
            ->orderBy($this->qualifyColumn($this->getModel()->getKeyName()));

        return $this;
    }

    public function minWeight(float $weight): static
    {
        // Multiplied by 1.0 so the (string) binding is compared as a number:
        // SQLite otherwise compares it as text, as the weight has no affinity.
        $this->whereRaw(self::WEIGHT_EXPRESSION.' >= (? * 1.0)', [$weight]);

        return $this;
    }

    public function whereRelatable(Model $model): static
    {
        return $this
            ->where($this->qualifyColumn('relatable_type'), $model->getMorphClass())
            ->where($this->qualifyColumn('relatable_id'), $model->getKey());
    }

    public function whereRelated(Model $model): static
    {
        return $this
            ->where($this->qualifyColumn('related_type'), $model->getMorphClass())
            ->where($this->qualifyColumn('related_id'), $model->getKey());
    }

    /**
     * @param  string  $type  A model class or its morph alias.
     */
    public function whereRelatedType(string $type): static
    {
        $morphType = is_a($type, Model::class, true) ? Relation::getMorphAlias($type) : $type;

        return $this->where($this->qualifyColumn('related_type'), $morphType);
    }
}
