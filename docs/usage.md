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
$action->attachRelated($genre, score: 0.5);
$action->attachRelated($chase, score: 0.4, boost: 2.0, options: ['source' => 'manual']);
```

Attaching a model that is already related updates that relation — a score or
boost left `null` keeps its current value. It returns the
`Foxws\Relatable\Models\Relatable` row.

### Mutual relations

Relations are directed, so `$action->attachRelated($genre)` only writes
*Action → Genre*: `$genre->relates` doesn't include *Action*. Pass
`mutual: true` to write the relation back as well, in the same transaction:

```php
$action->attachRelated($genre, score: 1.0, mutual: true, mutualScore: 0.5);

// Action → Genre: score 1.0
// Genre → Action: score 0.5
```

The relation back gets the same score and boost, unless you give it its own
with `mutualScore` and `mutualBoost`. This lets two models relate to each other
with a different weight each way — a genre may matter a lot to an action,
while the action is just one of many for the genre. The `options` are the same
for both.

Only the model you call the method on has its loaded relations refreshed. If
`$genre` already has `relatables` or `relates` loaded, reload it to see the
relation back:

```php
$genre->refresh();
```

## Detaching

```php
$action->detachRelated($genre);               // only Action → Genre
$action->detachRelated($genre, mutual: true); // and Genre → Action
```

## Syncing

`syncRelated()` relates exactly the given models: missing relations are
created, existing ones updated, and the rest removed. Each item is a model, or
an array with a `model` and an optional `score`, `boost` and `options`:

```php
$action->syncRelated([
    $fastpace,
    ['model' => $genre, 'score' => 0.5],
]);

$action->syncRelated([]); // remove all
```

A plain model keeps the score of an existing relation.

With `mutual: true`, the relations back are kept in sync too:

```php
$action->syncRelated([$fastpace, $genre], mutual: true);
```

- Each given model is related back to this one, with the same score and boost
  as its item (a plain model keeps the scores of an existing relation back).
  Use `attachRelated()` to give a relation back its own score.
- A relation back is only removed along with the relation that sync removes.
  So when *Genre → Action* exists without *Action → Genre*, syncing *Action*
  without *Genre* keeps it.

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
