# Laravel Relatable

This repository is a Laravel package. Keep the package focused, idiomatic, and easy for Laravel developers to install, test, and maintain.

## Package Conventions

- Use Laravel-native package APIs and the existing service provider shape before adding abstractions.
- Keep package names, namespaces, Composer metadata, publish tags, documentation, and examples aligned with `foxws/laravel-relatable`.
- Add only the files and dependencies needed for the package behavior being implemented.
- Prefer explicit Laravel package code over helper abstractions unless the extension point is real.
- Keep tests focused on observable package behavior through public APIs, service provider wiring, and published resources.
- Relations are directed: A → B and B → A are separate rows, each with its own `score` and `boost`. Their weight (`score × boost`) orders related models.
- Always resolve the row model through `Relatable::modelClass()`, so a configured subclass (and its `$table`) is used everywhere.

## Quick Commands

- Full validation: `composer test`
- Formatting check: `composer lint:check`
- Static analysis: `composer analyse` (PHPStan level 7)
- Pest tests: `composer test:unit`
- Type coverage: `composer test:types`

## Local Skills

- `relatable-development` (`resources/boost/skills/relatable-development/SKILL.md`): use when integrating `foxws/laravel-relatable` into a consuming Laravel application.
