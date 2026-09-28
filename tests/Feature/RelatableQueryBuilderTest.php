<?php

declare(strict_types=1);

use Foxws\Relatable\Models\Relatable;
use Foxws\Relatable\Tests\Fixtures\Models\Post;
use Foxws\Relatable\Tests\Fixtures\Models\Tag;

it('orders by weight and filters on a minimum weight', function () {
    $action = Tag::create(['name' => 'Action']);
    $foo = Tag::create(['name' => 'Foo']);
    $fastpace = Tag::create(['name' => 'Fastpace']);
    $post = Post::create(['title' => 'Chase']);

    $action->attachRelated($foo, score: 0.5);
    $action->attachRelated($fastpace, score: 1.0);
    $action->attachRelated($post, score: 0.2, boost: 2.0);

    $ordered = Relatable::query()->whereRelatable($action)->orderByWeight()->get();
    $heavy = Relatable::query()->minWeight(0.5)->orderByWeight('asc')->withWeight()->get();

    expect($ordered->models()->pluck('id', 'name')->keys()->all())->toBe(['Fastpace', 'Foo', ''])
        ->and($heavy->map(fn (Relatable $relatable): float => (float) $relatable->getAttributes()['weight'])->all())->toBe([0.5, 1.0])
        ->and(Relatable::query()->whereRelatedType(Post::class)->sole()->related->is($post))->toBeTrue();
});

it('rejects an invalid order direction', function () {
    Relatable::query()->orderByWeight('sideways');
})->throws(InvalidArgumentException::class);
