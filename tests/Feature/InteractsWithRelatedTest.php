<?php

declare(strict_types=1);

use Foxws\Relatable\Models\Relatable;
use Foxws\Relatable\Tests\Fixtures\Models\Post;
use Foxws\Relatable\Tests\Fixtures\Models\Tag;

it('relates a model with the default score and boost', function () {
    $action = Tag::create(['name' => 'Action']);
    $fastpace = Tag::create(['name' => 'Fastpace']);

    $relatable = $action->attachRelated($fastpace);

    expect($relatable->relatable->is($action))->toBeTrue()
        ->and($relatable->related->is($fastpace))->toBeTrue()
        ->and($relatable->score)->toBe(1.0)
        ->and($relatable->boost)->toBe(1.0)
        ->and($relatable->weight)->toBe(1.0);

    $this->assertDatabaseCount('relatables', 1);
});

it('only relates one way unless mutual', function () {
    $action = Tag::create(['name' => 'Action']);
    $fastpace = Tag::create(['name' => 'Fastpace']);

    $action->attachRelated($fastpace);

    expect($action->relates)->toHaveCount(1)
        ->and($fastpace->relates)->toBeEmpty();
});

it('updates the score of an existing relation without duplicating it', function () {
    $action = Tag::create(['name' => 'Action']);
    $foo = Tag::create(['name' => 'Foo']);

    $action->attachRelated($foo, score: 0.5, boost: 2.0);
    $relatable = $action->attachRelated($foo, score: 0.25);

    expect($relatable->score)->toBe(0.25)
        ->and($relatable->boost)->toBe(2.0)
        ->and($relatable->weight)->toBe(0.5);

    $this->assertDatabaseCount('relatables', 1);
});

it('relates both ways with their own scores when mutual', function () {
    $action = Tag::create(['name' => 'Action']);
    $foo = Tag::create(['name' => 'Foo']);

    $action->attachRelated($foo, score: 1.0, mutual: true, mutualScore: 0.5);

    expect(Relatable::query()->whereRelatable($action)->sole()->score)->toBe(1.0)
        ->and(Relatable::query()->whereRelatable($foo)->sole()->score)->toBe(0.5);
});

it('detaches a relation, and the one back when mutual', function () {
    $action = Tag::create(['name' => 'Action']);
    $foo = Tag::create(['name' => 'Foo']);
    $bar = Tag::create(['name' => 'Bar']);

    $action->attachRelated($foo, mutual: true);
    $action->attachRelated($bar, mutual: true);

    expect($action->detachRelated($foo))->toBeTrue()
        ->and($action->detachRelated($foo))->toBeFalse()
        ->and($foo->relates->first()->is($action))->toBeTrue()
        ->and($action->detachRelated($bar, mutual: true))->toBeTrue();

    $this->assertDatabaseCount('relatables', 1);
});

it('orders related models by weight across model types', function () {
    $action = Tag::create(['name' => 'Action']);
    $foo = Tag::create(['name' => 'Foo']);
    $fastpace = Tag::create(['name' => 'Fastpace']);
    $post = Post::create(['title' => 'Chase']);

    $action->attachRelated($foo, score: 0.5);
    $action->attachRelated($post, score: 0.4, boost: 2.0);
    $action->attachRelated($fastpace, score: 1.0);

    expect($action->relates->map(fn ($model) => $model->getAttribute('name') ?? $model->getAttribute('title'))->all())
        ->toBe(['Fastpace', 'Chase', 'Foo'])
        ->and($action->getRelates(Post::class))->toHaveCount(1);
});

it('refreshes the related models after changing relations', function () {
    $action = Tag::create(['name' => 'Action']);
    $foo = Tag::create(['name' => 'Foo']);

    expect($action->relates)->toBeEmpty();

    $action->attachRelated($foo);

    expect($action->relates)->toHaveCount(1);
});

it('syncs related models with scores, removing stale relations', function () {
    $action = Tag::create(['name' => 'Action']);
    $foo = Tag::create(['name' => 'Foo']);
    $fastpace = Tag::create(['name' => 'Fastpace']);

    $action->syncRelated([$foo, $fastpace]);
    $action->syncRelated([
        ['model' => $fastpace, 'score' => 0.8],
    ]);

    expect($action->relates)->toHaveCount(1)
        ->and($action->relates->first()->is($fastpace))->toBeTrue()
        ->and(Relatable::query()->whereRelated($fastpace)->sole()->score)->toBe(0.8);

    $this->assertDatabaseCount('relatables', 1);
});

it('keeps existing scores when syncing plain models', function () {
    $action = Tag::create(['name' => 'Action']);
    $foo = Tag::create(['name' => 'Foo']);

    $action->attachRelated($foo, score: 0.5);
    $action->syncRelated([$foo]);

    expect(Relatable::query()->sole()->score)->toBe(0.5);
});

it('syncs the relations back when mutual', function () {
    $action = Tag::create(['name' => 'Action']);
    $foo = Tag::create(['name' => 'Foo']);
    $bar = Tag::create(['name' => 'Bar']);

    $action->syncRelated([$foo, $bar], mutual: true);
    $action->syncRelated([$bar], mutual: true);

    expect($foo->relates)->toBeEmpty()
        ->and($bar->relates->first()->is($action))->toBeTrue();

    $this->assertDatabaseCount('relatables', 2);
});

it('rejects invalid items when syncing', function () {
    Tag::create(['name' => 'Action'])->syncRelated(['foo']);
})->throws(InvalidArgumentException::class);

it('removes relations in both directions when a model is deleted', function () {
    $action = Tag::create(['name' => 'Action']);
    $foo = Tag::create(['name' => 'Foo']);
    $bar = Tag::create(['name' => 'Bar']);

    $action->attachRelated($foo);
    $bar->attachRelated($action);
    $foo->attachRelated($bar);

    $action->delete();

    $this->assertDatabaseCount('relatables', 1);
});

it('keeps relations of soft-deleted models until force-deleted', function () {
    $post = Post::create(['title' => 'Chase']);
    $post->attachRelated(Tag::create(['name' => 'Action']));

    $post->delete();

    $this->assertDatabaseCount('relatables', 1);

    $post->forceDelete();

    $this->assertDatabaseCount('relatables', 0);
});

it('keeps relations on delete when disabled', function () {
    config()->set('relatable.delete_on_model_delete', false);

    $action = Tag::create(['name' => 'Action']);
    $action->attachRelated(Tag::create(['name' => 'Foo']));

    $action->delete();

    $this->assertDatabaseCount('relatables', 1);
});

it('uses the configured default score and boost', function () {
    config()->set('relatable.defaults.score', 0.5);
    config()->set('relatable.defaults.boost', 3.0);

    $relatable = Tag::create(['name' => 'Action'])->attachRelated(Tag::create(['name' => 'Foo']));

    expect($relatable->weight)->toBe(1.5);
});
