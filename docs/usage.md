---
section: Getting Started
order: 2
---

# Usage

Add the `InteractsWithRelated` trait to each model that relates to others. The
models it relates *to* don't need the trait, unless they relate back.

```php
use Foxws\Relatable\Concerns\InteractsWithRelated;

class Tag extends Model
{
    use InteractsWithRelated;
}
```

## Attaching

```php
$action->attachRelated($fastpace);            // score and boost default to 1.0
$action->attachRelated($foo, score: 0.5);
$action->attachRelated($chase, score: 0.4, boost: 2.0, options: ['source' => 'manual']);
```

Attaching a model that is already related updates that relation — a score or
boost left `null` keeps its current value. It returns the
`Foxws\Relatable\Models\Relatable` row.

### Mutual relations

Pass `mutual: true` to relate the model back to this one as well. The relation
back gets the same score and boost, unless you give it its own:

```php
$action->attachRelated($foo, score: 1.0, mutual: true, mutualScore: 0.5);
```

## Detaching

```php
$action->detachRelated($foo);               // only Action → Foo
$action->detachRelated($foo, mutual: true); // and Foo → Action
```

## Syncing

`syncRelated()` relates exactly the given models: missing relations are
created, existing ones updated, and the rest removed. Each item is a model, or
an array with a `model` and an optional `score`, `boost` and `options`:

```php
$action->syncRelated([
    $fastpace,
    ['model' => $foo, 'score' => 0.5],
]);

$action->syncRelated([]); // remove all
```

A plain model keeps the score of an existing relation. With `mutual: true`, the
relations back are created and removed along with them.

## Retrieving related models

```php
$action->relates;                  // cached attribute, highest weight first
$action->getRelates();             // same, uncached
$action->getRelates(Video::class); // only videos (a class or morph alias)
```

Related models are eager loaded per type, so this is one query per related
model type. To eager load them for many models at once:

```php
Tag::with('relatables.related')->get();
```

## Relations

```php
$action->relatables();     // MorphMany: Action → others
$action->relatablesFrom(); // MorphMany: others → Action
```

## Deleting models

Deleting a model deletes its relations in both directions. Soft-deleted models
keep them until they're force-deleted. Disable this with
`relatable.delete_on_model_delete`.
