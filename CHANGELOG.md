# Changelog

All notable changes to `laravel-relatable` will be documented in this file.

## v3.0.0 - 2026-11-23

**Breaking changes:**

- The migration always creates the `relatables` table. A configured model with its own `$table` needs `relatable.migrations` set to `false`.
- After publishing the migration, set `relatable.migrations` to `false`, or both copies run and the second fails with "table already exists".
- Scores and boosts must be finite numbers: `INF`, `NAN` and values like `'1e999'` (which pass Laravel's `numeric` rule) throw an `InvalidArgumentException`, as does a non-numeric `score` or `boost` in `syncRelated()`.
- `relates` and `getRelates()` no longer load `relatables` on the model, so its array and JSON don't include every relation and its options. Eager load `relatables.related` as before for lists.
- `RelatableQueryBuilder::WEIGHT_EXPRESSION` is replaced by `weightExpression()`.

**Fixes:**

- The unique index is now named `relatables_relation_unique`, so the migration works on MySQL and MariaDB (the generated name was longer than the 64 characters they allow). Existing tables keep their index.
- Relations are read and written on the connection of the model they start from, instead of the default connection.
- Relations are removed once the model is deleted, so a cancelled or failed delete keeps them.
- `detachRelated()` keeps going when one row's delete is cancelled, a mutual sync may include the model itself, and two requests creating the same relation no longer fail on the unique index.
- Integer defaults in `relatable.defaults` work, models with object keys (such as `Stringable`) sync correctly, `relatablesFrom` is refreshed after a mutual change, and the weight methods work on joins with other `score` or `boost` columns.

<!-- Release notes generated using configuration in .github/release.yml at 8182a2bff3f9d1802361f8260c5974e9c1f1bdbc -->
### What's Changed

#### Other Changes

* Remove the code style workflow and pin the actions by @francoism90 in https://github.com/foxws/laravel-relatable/pull/10
* Harden reading and writing relations by @francoism90 in https://github.com/foxws/laravel-relatable/pull/12
* Fix the migration on MySQL and after publishing it by @francoism90 in https://github.com/foxws/laravel-relatable/pull/11

**Full Changelog**: https://github.com/foxws/laravel-relatable/compare/v2.0.0...v3.0.0

## v2.0.0 - 2026-11-19

This release requires PHP 8.5 or newer and Laravel 13. PHP 8.4 and Laravel 12 are no longer supported, and PHP 8.6 is now tested in CI. Stay on the previous major version if you still run PHP 8.4 or Laravel 12.

<!-- Release notes generated using configuration in .github/release.yml at a8338a14dc3ed2b71ad2609a38a5fcd7df710f98 -->
### What's Changed

#### Other Changes

* Drop PHP 8.4 and add PHP 8.6 by @francoism90 in https://github.com/foxws/laravel-relatable/pull/9

**Full Changelog**: https://github.com/foxws/laravel-relatable/compare/v1.1.0...v2.0.0

## v1.1.0 - 2026-11-12

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Other Changes

* Reuse the loaded relations when syncing instead of querying each model by @francoism90 in https://github.com/foxws/laravel-relatable/pull/8

**Full Changelog**: https://github.com/foxws/laravel-relatable/compare/v1.0.3...v1.1.0

## v1.0.3 - 2026-10-14

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Other Changes

* Add Laravel Boost tip to README and docs by @francoism90 in https://github.com/foxws/laravel-relatable/pull/7

**Full Changelog**: https://github.com/foxws/laravel-relatable/compare/v1.0.2...v1.0.3

## v1.0.2 - 2026-10-13

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Other Changes

* Fix docs links in Boost skill by @francoism90 in https://github.com/foxws/laravel-relatable/pull/6

**Full Changelog**: https://github.com/foxws/laravel-relatable/compare/v1.0.1...v1.0.2

## v1.0.1 - 2026-10-02

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Other Changes

* docs: explain mutual relations in more detail by @francoism90 in https://github.com/foxws/laravel-relatable/pull/2
* docs: add the foxws.nl homepage group, a hero lead and a clearer introduction by @francoism90 in https://github.com/foxws/laravel-relatable/pull/3
* Raise PHPStan to level 8 by @francoism90 in https://github.com/foxws/laravel-relatable/pull/5

**Full Changelog**: https://github.com/foxws/laravel-relatable/compare/v1.0.0...v1.0.1

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
