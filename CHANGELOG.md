# Changelog

All notable changes to `laravel-relatable` will be documented in this file.

## v1.0.0 - 2026-09-28

First release of Laravel Relatable: relate Eloquent models to other models, of any type, with a base score and boost to control their priority.

### Features

- **Directed relations:** A → B and B → A are separate relations, each with its own `score` and `boost`.
- **Weighted ordering:** related models are ordered by weight (`score × boost`), highest first.
- **`InteractsWithRelated` trait:** `attachRelated()`, `detachRelated()`, `syncRelated()`, `getRelates()` and a cached `relates` attribute.
- **Mutual relations:** relate both ways in one call, optionally with a different score back.
- **Query builder:** `whereRelatable()`, `whereRelated()`, `whereRelatedType()`, `minWeight()`, `orderByWeight()` and `withWeight()`.
- **Cleanup:** deleting a model deletes its relations in both directions; soft-deleted models keep them until force-deleted.
- **Configurable model:** use your own subclass, and your own table, via `relatable.models.relatable`.
- **Laravel Boost skill:** `relatable-development`.

Supports PHP 8.3+ and Laravel 12 and 13.

**Full documentation:** https://github.com/foxws/laravel-relatable/tree/v1.0.0/docs
