<?php

declare(strict_types=1);

use Foxws\Relatable\RelatableServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

it('keeps its index names within the length MySQL allows', function () {
    $names = collect(Schema::getIndexes('relatables'))->pluck('name');

    expect($names)->toContain('relatables_relation_unique')
        ->and($names->filter(fn (string $name): bool => strlen($name) > 64))->toBeEmpty();
});

it('does not run its own migration once it is published', function () {
    $published = database_path('migrations/2026_11_23_000000_create_relatables_table.php');

    File::copy(__DIR__.'/../../database/migrations/2026_09_28_000001_create_relatables_table.php', $published);

    try {
        $this->refreshApplication();

        $paths = app('migrator')->paths();
    } finally {
        File::delete($published);
    }

    expect($paths)->not->toContain(realpath(__DIR__.'/../../src').'/../database/migrations');
});

it('runs its own migration when it is not published', function () {
    expect(app()->getProvider(RelatableServiceProvider::class))->not->toBeNull()
        ->and(app('migrator')->paths())->toContain(realpath(__DIR__.'/../../src').'/../database/migrations');
});
