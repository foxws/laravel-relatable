<?php

declare(strict_types=1);

namespace Foxws\Relatable\Concerns;

use BackedEnum;
use Foxws\Relatable\Collections\RelatableCollection;
use Foxws\Relatable\Models\Relatable;
use Foxws\Relatable\QueryBuilders\RelatableQueryBuilder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Stringable;

/**
 * @property-read RelatableCollection<int, Relatable> $relatables
 * @property-read RelatableCollection<int, Relatable> $relatablesFrom
 * @property-read Collection<int, Model> $relates
 */
trait InteractsWithRelated
{
    public static function bootInteractsWithRelated(): void
    {
        // Soft-deleted models keep their relations until force-deleted. They are
        // removed once the delete succeeded, so a cancelled or failed one keeps them.
        $event = in_array(SoftDeletes::class, class_uses_recursive(static::class), true)
            ? 'forceDeleted'
            : 'deleted';

        static::registerModelEvent($event, function (self $model): void {
            if (! config()->boolean('relatable.delete_on_model_delete', true)) {
                return;
            }

            $model->relatables()->cursor()->each(function (Relatable $relatable): void {
                $relatable->delete();
            });

            $model->relatablesFrom()->cursor()->each(function (Relatable $relatable): void {
                $relatable->delete();
            });
        });
    }

    /**
     * The relations from this model to others.
     *
     * @return MorphMany<Relatable, $this>
     */
    public function relatables(): MorphMany
    {
        return $this->morphMany(Relatable::modelClass(), 'relatable')->chaperone();
    }

    /**
     * The relations from other models to this one.
     *
     * @return MorphMany<Relatable, $this>
     */
    public function relatablesFrom(): MorphMany
    {
        return $this->morphMany(Relatable::modelClass(), 'related');
    }

    /**
     * Relate the given model to this one, or update the existing relation.
     * A score or boost left null keeps its current value, or gets the
     * configured default on a new relation. When mutual, the given model
     * is related back to this one too, with its own score and boost
     * (defaulting to the same ones).
     *
     * @param  array<array-key, mixed>|null  $options
     */
    public function attachRelated(
        Model $model,
        ?float $score = null,
        ?float $boost = null,
        ?array $options = null,
        bool $mutual = false,
        ?float $mutualScore = null,
        ?float $mutualBoost = null,
    ): Relatable {
        $relatable = $this->getConnection()->transaction(function () use ($model, $score, $boost, $options, $mutual, $mutualScore, $mutualBoost): Relatable {
            if ($mutual) {
                static::writeRelatable($model, $this, $mutualScore ?? $score, $mutualBoost ?? $boost, $options);
            }

            return static::writeRelatable($this, $model, $score, $boost, $options);
        });

        $this->flushRelatedCache();

        return $relatable;
    }

    /**
     * Remove the relation to the given model, and the one back when mutual.
     */
    public function detachRelated(Model $model, bool $mutual = false): bool
    {
        $deleted = $this->getConnection()->transaction(function () use ($model, $mutual): bool {
            $relatables = static::relatableQuery($this)
                ->whereRelatable($this)
                ->whereRelated($model)
                ->get();

            if ($mutual) {
                $relatables = $relatables->merge(
                    static::relatableQuery($this)->whereRelatable($model)->whereRelated($this)->get(),
                );
            }

            return $relatables
                ->filter(fn (Relatable $relatable): bool => (bool) $relatable->delete())
                ->isNotEmpty();
        });

        $this->flushRelatedCache();

        return $deleted;
    }

    /**
     * Relate exactly the given models to this one: missing relations are
     * created, existing ones updated and the rest removed. Each item is a
     * model, or an array with a `model` and optional `score`, `boost` and
     * `options`. When mutual, the relations back are kept in sync too.
     *
     * @param  iterable<array-key, Model|array{model: Model, score?: float|int|null, boost?: float|int|null, options?: array<array-key, mixed>|null}>  $items
     */
    public function syncRelated(iterable $items = [], bool $mutual = false): static
    {
        $items = Collection::make($this->normalizeRelated($items))
            ->keyBy(fn (array $item): string => $this->relatedKey($item['model']->getMorphClass(), $item['model']->getKey()));

        $this->getConnection()->transaction(function () use ($items, $mutual): void {
            $existing = $this->relatables()
                ->get()
                ->keyBy(fn (Relatable $relatable): string => $this->relatedKey($relatable->related_type, $relatable->related_id));

            $existing
                ->reject(fn (Relatable $relatable, string $key): bool => $items->has($key))
                ->each(function (Relatable $relatable) use ($mutual): void {
                    $relatable->delete();

                    if (! $mutual) {
                        return;
                    }

                    static::relatableQuery($this)
                        ->where('relatable_type', $relatable->related_type)
                        ->where('relatable_id', $relatable->related_id)
                        ->whereRelated($this)
                        ->get()
                        ->each(function (Relatable $reverse): void {
                            $reverse->delete();
                        });
                });

            // The relations from this model were loaded above, so only the ones back are looked up.
            $items->each(function (array $item, string $key) use ($existing, $mutual): void {
                if ($mutual && ! $item['model']->is($this)) {
                    static::writeRelatable($item['model'], $this, $item['score'], $item['boost'], $item['options']);
                }

                static::fillRelatable(
                    $existing->get($key) ?? static::newRelatable($this, $item['model']),
                    $item['score'],
                    $item['boost'],
                    $item['options'],
                );
            });
        });

        $this->flushRelatedCache();

        return $this;
    }

    /**
     * The related models, highest weight first. Pass a model class or
     * morph alias to only get models of that type.
     *
     * @return Collection<int, Model>
     */
    public function getRelates(?string $type = null): Collection
    {
        // Only reuse loaded relations: loading them here would add every relation,
        // of every type and with its options, to the model's array and JSON.
        /** @var RelatableCollection<int, Relatable> $relatables */
        $relatables = $this->relationLoaded('relatables')
            ? $this->getRelation('relatables')
            : $this->relatables()->get();

        $relatables->loadMissing('related');

        if ($type !== null) {
            $morphType = is_a($type, Model::class, true) ? Relation::getMorphAlias($type) : $type;

            $relatables = $relatables->filter(fn (Relatable $relatable): bool => $relatable->related_type === $morphType);
        }

        return $relatables->sortByWeight()->models();
    }

    /**
     * @return Attribute<Collection<int, Model>, never>
     */
    protected function relates(): Attribute
    {
        return Attribute::get(fn (): Collection => $this->getRelates())->shouldCache();
    }

    /**
     * @param  array<array-key, mixed>|null  $options
     */
    protected static function writeRelatable(Model $from, Model $to, ?float $score, ?float $boost, ?array $options): Relatable
    {
        $relatable = static::relatableQuery($from)
            ->whereRelatable($from)
            ->whereRelated($to)
            ->first();

        return static::fillRelatable($relatable ?? static::newRelatable($from, $to), $score, $boost, $options);
    }

    protected static function newRelatable(Model $from, Model $to): Relatable
    {
        return static::newRelatableInstance($from)->fill([
            'relatable_type' => $from->getMorphClass(),
            'relatable_id' => $from->getKey(),
            'related_type' => $to->getMorphClass(),
            'related_id' => $to->getKey(),
        ]);
    }

    /**
     * A relation row on the connection of the model it starts from, unless
     * the configured model sets its own, like relatables() does.
     */
    protected static function newRelatableInstance(Model $model): Relatable
    {
        $class = Relatable::modelClass();

        $relatable = new $class;

        if (! $relatable->getConnectionName()) {
            $relatable->setConnection($model->getConnectionName());
        }

        return $relatable;
    }

    /**
     * @return RelatableQueryBuilder<Relatable>
     */
    protected static function relatableQuery(Model $model): RelatableQueryBuilder
    {
        /** @var RelatableQueryBuilder<Relatable> */
        return static::newRelatableInstance($model)->newQuery();
    }

    /**
     * Save the given score, boost and options; a new relation gets the
     * configured default for the ones left null.
     *
     * @param  array<array-key, mixed>|null  $options
     */
    protected static function fillRelatable(Relatable $relatable, ?float $score, ?float $boost, ?array $options): Relatable
    {
        foreach (['score' => $score, 'boost' => $boost] as $name => $value) {
            if ($value !== null && ! is_finite($value)) {
                throw new InvalidArgumentException("The {$name} of a relation must be a finite number.");
            }
        }

        $attributes = array_filter(
            ['score' => $score, 'boost' => $boost, 'options' => $options],
            fn (mixed $value): bool => $value !== null,
        );

        if ($relatable->exists) {
            $relatable->fill($attributes)->save();

            return $relatable;
        }

        // Another request may have created the same relation in the meantime.
        /** @var Relatable $created */
        $created = $relatable->newQuery()->createOrFirst(
            $relatable->only(['relatable_type', 'relatable_id', 'related_type', 'related_id']),
            $attributes + [
                'score' => (float) config('relatable.defaults.score', 1.0),
                'boost' => (float) config('relatable.defaults.boost', 1.0),
            ],
        );

        if (! $created->wasRecentlyCreated) {
            $created->fill($attributes)->save();
        }

        return $created;
    }

    /**
     * @param  iterable<array-key, mixed>  $items
     * @return list<array{model: Model, score: float|null, boost: float|null, options: array<array-key, mixed>|null}>
     */
    protected function normalizeRelated(iterable $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            $normalized[] = $this->normalizeRelatedItem($item);
        }

        return $normalized;
    }

    /**
     * @return array{model: Model, score: float|null, boost: float|null, options: array<array-key, mixed>|null}
     */
    protected function normalizeRelatedItem(mixed $item): array
    {
        if ($item instanceof Model) {
            return ['model' => $item, 'score' => null, 'boost' => null, 'options' => null];
        }

        if (! is_array($item) || ! ($item['model'] ?? null) instanceof Model) {
            throw new InvalidArgumentException('Each related item must be a model, or an array with a "model" key.');
        }

        foreach (['score', 'boost'] as $name) {
            if (isset($item[$name]) && ! is_numeric($item[$name])) {
                throw new InvalidArgumentException("The {$name} of a related item must be a number.");
            }
        }

        return [
            'model' => $item['model'],
            'score' => isset($item['score']) ? (float) $item['score'] : null,
            'boost' => isset($item['boost']) ? (float) $item['boost'] : null,
            'options' => isset($item['options']) && is_array($item['options']) ? $item['options'] : null,
        ];
    }

    protected function relatedKey(string $type, mixed $id): string
    {
        $id = $id instanceof BackedEnum ? $id->value : $id;

        if (! is_scalar($id) && ! $id instanceof Stringable) {
            throw new InvalidArgumentException('Related models must have a scalar or stringable key.');
        }

        return sprintf('%s::%s', $type, (string) $id);
    }

    protected function flushRelatedCache(): void
    {
        $this->unsetRelation('relatables');
        $this->unsetRelation('relatablesFrom');

        unset($this->attributeCastCache['relates']);
    }
}
