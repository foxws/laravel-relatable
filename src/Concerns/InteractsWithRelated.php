<?php

declare(strict_types=1);

namespace Foxws\Relatable\Concerns;

use Foxws\Relatable\Collections\RelatableCollection;
use Foxws\Relatable\Models\Relatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * @property-read RelatableCollection<int, Relatable> $relatables
 * @property-read RelatableCollection<int, Relatable> $relatablesFrom
 * @property-read Collection<int, Model> $relates
 */
trait InteractsWithRelated
{
    public static function bootInteractsWithRelated(): void
    {
        // Soft-deleted models keep their relations until force-deleted.
        $event = in_array(SoftDeletes::class, class_uses_recursive(static::class), true)
            ? 'forceDeleting'
            : 'deleting';

        static::registerModelEvent($event, function (self $model): void {
            if (! config()->boolean('relatable.delete_on_model_delete', true)) {
                return;
            }

            $model->relatables()->cursor()->each(fn (Relatable $relatable): ?bool => $relatable->delete());
            $model->relatablesFrom()->cursor()->each(fn (Relatable $relatable): ?bool => $relatable->delete());
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
            $relatables = Relatable::modelClass()::query()
                ->whereRelatable($this)
                ->whereRelated($model)
                ->get();

            if ($mutual) {
                $relatables = $relatables->merge(
                    Relatable::modelClass()::query()->whereRelatable($model)->whereRelated($this)->get(),
                );
            }

            $relatables->each(fn (Relatable $relatable): ?bool => $relatable->delete());

            return $relatables->isNotEmpty();
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
            $this->relatables()
                ->get()
                ->reject(fn (Relatable $relatable): bool => $items->has($this->relatedKey($relatable->related_type, $relatable->related_id)))
                ->each(function (Relatable $relatable) use ($mutual): void {
                    $relatable->delete();

                    if (! $mutual) {
                        return;
                    }

                    Relatable::modelClass()::query()
                        ->where('relatable_type', $relatable->related_type)
                        ->where('relatable_id', $relatable->related_id)
                        ->whereRelated($this)
                        ->get()
                        ->each(fn (Relatable $reverse): ?bool => $reverse->delete());
                });

            $items->each(fn (array $item): Relatable => $this->attachRelated(
                $item['model'],
                $item['score'],
                $item['boost'],
                $item['options'],
                $mutual,
            ));
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
        $this->loadMissing('relatables.related');

        /** @var RelatableCollection<int, Relatable> $relatables */
        $relatables = $this->getRelation('relatables');

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
        $relatable = Relatable::modelClass()::query()->firstOrNew([
            'relatable_type' => $from->getMorphClass(),
            'relatable_id' => $from->getKey(),
            'related_type' => $to->getMorphClass(),
            'related_id' => $to->getKey(),
        ]);

        $attributes = array_filter(
            ['score' => $score, 'boost' => $boost, 'options' => $options],
            fn (mixed $value): bool => $value !== null,
        );

        if (! $relatable->exists) {
            $attributes += [
                'score' => config()->float('relatable.defaults.score', 1.0),
                'boost' => config()->float('relatable.defaults.boost', 1.0),
            ];
        }

        $relatable->fill($attributes)->save();

        return $relatable;
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

        return [
            'model' => $item['model'],
            'score' => isset($item['score']) && is_numeric($item['score']) ? (float) $item['score'] : null,
            'boost' => isset($item['boost']) && is_numeric($item['boost']) ? (float) $item['boost'] : null,
            'options' => isset($item['options']) && is_array($item['options']) ? $item['options'] : null,
        ];
    }

    protected function relatedKey(string $type, mixed $id): string
    {
        return sprintf('%s::%s', $type, is_scalar($id) ? (string) $id : '');
    }

    protected function flushRelatedCache(): void
    {
        $this->unsetRelation('relatables');

        unset($this->attributeCastCache['relates']);
    }
}
