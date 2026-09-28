---
section: Concepts
order: 3
---

# Scoring

Each relation has two numbers:

- `score` — the base relevance of the relation, typically between `0` and `1`.
- `boost` — a multiplier on top of that, `1.0` by default.

The relation's **weight** is `score × boost`. Related models are ordered by
weight, highest first; relations of equal weight keep the order they were
created in.

```php
$action->attachRelated($fastpace, score: 1.0); // weight 1.0
$action->attachRelated($chase, score: 0.4, boost: 2.0); // weight 0.8
$action->attachRelated($genre, score: 0.5); // weight 0.5

$action->relates; // Fastpace, Chase, Genre
```

Use `score` for how related two models are, and `boost` to promote (or demote)
a relation without losing that base score — e.g. to feature a relation for a
while, then reset its boost to `1.0`.

```php
$relatable = $action->attachRelated($genre);

$relatable->score;  // 1.0
$relatable->boost;  // 1.0
$relatable->weight; // 1.0
```

The defaults for new relations are set in `relatable.defaults`.

## Querying by weight

`Relatable::query()` returns a `RelatableQueryBuilder`:

```php
use Foxws\Relatable\Models\Relatable;

Relatable::query()
    ->whereRelatable($action)          // relations from Action
    ->whereRelatedType(Video::class)   // to videos
    ->minWeight(0.5)                   // with a weight of at least 0.5
    ->orderByWeight()                  // highest first ('asc' for lowest)
    ->withWeight()                     // select the weight as a column
    ->with('related')
    ->get()
    ->models();                        // the related models, in order
```

`whereRelated($model)` finds relations *to* a model. A collection of
relations also has `sortByWeight()`.
