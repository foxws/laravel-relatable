<div align="center">
    <h1>Laravel Relatable</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/foxws/laravel-relatable"><img src="https://img.shields.io/packagist/v/foxws/laravel-relatable.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/foxws/laravel-relatable"><img src="https://img.shields.io/packagist/php-v/foxws/laravel-relatable.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://github.com/foxws/laravel-relatable/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/foxws/laravel-relatable/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/foxws/laravel-relatable"><img src="https://img.shields.io/packagist/dt/foxws/laravel-relatable.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Relate Eloquent models to other models — of any type — and control how strongly they relate. Each relation has a base `score` and a `boost`; their product, the relation's **weight**, orders the related models.

Relations are directed: *Action* can relate to *Fastpace* with a score of `1.0` and to *Genre* with `0.5`, while *Genre* relates back to *Action* with a score of its own.

## Installation

You can install the package via Composer:

```bash
composer require foxws/laravel-relatable
```

The migration for the `relatables` table runs automatically. You may publish all of the package's resources at once:

```bash
php artisan vendor:publish --tag="relatable"
```

Or, you may publish each resource individually:

```bash
php artisan vendor:publish --tag="relatable-config"
php artisan vendor:publish --tag="relatable-migrations"
```

## Usage

Add the `InteractsWithRelated` trait to each model that relates to others:

```php
use Foxws\Relatable\Concerns\InteractsWithRelated;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use InteractsWithRelated;
}
```

Then relate models, with an optional score and boost:

```php
$action->attachRelated($fastpace, score: 1.0);
$action->attachRelated($genre, score: 0.5);

// Relations are directed. Relate both ways (Action → Genre and Genre → Action),
// with a lower score back.
$action->attachRelated($genre, score: 0.5, mutual: true, mutualScore: 0.25);

// Relate exactly these models, removing the rest.
$action->syncRelated([
    $fastpace,
    ['model' => $genre, 'score' => 0.5, 'boost' => 2.0],
]);

$action->detachRelated($genre);               // only Action → Genre
$action->detachRelated($genre, mutual: true); // and Genre → Action

$action->relates; // Collection of related models, highest weight first
$action->getRelates(Video::class); // only related videos
```

See the [documentation](docs/index.md) for mutual relations, scoring, querying relations, and configuration.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Laravel Relatable! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [francoism90](https://github.com/francoism90)
- [All Contributors](../../contributors)

Inspired by [spatie/laravel-relatable](https://github.com/spatie/laravel-relatable).

## License

Laravel Relatable is open-sourced software licensed under the [MIT license](LICENSE.md).
