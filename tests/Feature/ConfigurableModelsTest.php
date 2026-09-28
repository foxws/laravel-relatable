<?php

declare(strict_types=1);

use Foxws\Relatable\Models\Relatable;
use Foxws\Relatable\Tests\Fixtures\Models\LegacyRelated;
use Foxws\Relatable\Tests\Fixtures\Models\Tag;
use Illuminate\Support\Facades\Schema;

it('resolves the base relatable class by default', function () {
    expect(Relatable::modelClass())->toBe(Relatable::class);
});

it('uses a configured model with its own table', function () {
    config()->set('relatable.models.relatable', LegacyRelated::class);

    Schema::rename('relatables', 'related');

    $action = Tag::create(['name' => 'Action']);
    $foo = Tag::create(['name' => 'Foo']);

    $relatable = $action->attachRelated($foo, score: 0.5);

    expect($relatable)->toBeInstanceOf(LegacyRelated::class)
        ->and(LegacyRelated::query()->whereRelatable($action)->orderByWeight()->get()->models()->first()->is($foo))->toBeTrue()
        ->and($action->relatables()->getRelated())->toBeInstanceOf(LegacyRelated::class)
        ->and($action->relates->first()->is($foo))->toBeTrue();

    $action->syncRelated([]);

    $this->assertDatabaseCount('related', 0);
});
