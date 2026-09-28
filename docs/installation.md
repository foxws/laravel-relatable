---
section: Getting Started
order: 1
---

# Installation

```bash
composer require foxws/laravel-relatable
```

The migration for the `relatables` table runs automatically — no
`vendor:publish` step is required, but it can still be published (and
customized) via:

```bash
php artisan vendor:publish --tag="relatable-migrations"
php artisan migrate
```

Publish the config file:

```bash
php artisan vendor:publish --tag="relatable-config"
```

> [!WARNING]
> Don't add your own migration for the `relatables` table — both would run,
> failing with a "relation already exists" error. Publish and edit the
> package's migration instead, or disable it (see
> [configuration.md](configuration.md#using-an-existing-table)).

Then add the `InteractsWithRelated` trait to your models (see
[usage.md](usage.md)).
