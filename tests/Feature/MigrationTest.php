<?php

declare(strict_types=1);

use Foxws\Relatable\RelatableServiceProvider;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Boot the provider again against a database path of its own, so parallel
 * test processes don't share published migrations.
 *
 * @return list<string>
 */
function migrationPathsAfterBooting(bool $published): array
{
    $databasePath = sys_get_temp_dir().'/relatable-'.uniqid();

    File::ensureDirectoryExists("{$databasePath}/migrations");

    if ($published) {
        File::copy(
            __DIR__.'/../../database/migrations/2026_09_28_000001_create_relatables_table.php',
            "{$databasePath}/migrations/2026_11_23_000000_create_relatables_table.php",
        );
    }

    try {
        app()->useDatabasePath($databasePath);

        $migrator = new Migrator(app('migration.repository'), app('db'), app('files'), app('events'));

        app()->instance('migrator', $migrator);

        (new RelatableServiceProvider(app()))->boot();

        return array_values($migrator->paths());
    } finally {
        File::deleteDirectory($databasePath);
    }
}

it('keeps its index names within the length MySQL allows', function () {
    $names = collect(Schema::getIndexes('relatables'))->pluck('name');

    expect($names)->toContain('relatables_relation_unique')
        ->and($names->filter(fn (string $name): bool => strlen($name) > 64))->toBeEmpty();
});

it('runs its own migration when it is not published', function () {
    expect(migrationPathsAfterBooting(published: false))->toHaveCount(1);
});

it('does not run its own migration once it is published', function () {
    expect(migrationPathsAfterBooting(published: true))->toBe([]);
});
