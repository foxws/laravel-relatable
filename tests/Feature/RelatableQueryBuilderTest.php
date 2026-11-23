<?php

declare(strict_types=1);

use Foxws\Relatable\Models\Relatable;
use Foxws\Relatable\Tests\Fixtures\Models\Post;
use Foxws\Relatable\Tests\Fixtures\Models\Tag;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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

it('orders by weight when joined with a table that also has a score', function () {
    Schema::create('reviews', function (Blueprint $table) {
        $table->unsignedBigInteger('relatable_id');
        $table->float('score');
    });

    $action = Tag::create(['name' => 'Action']);
    $action->attachRelated(Tag::create(['name' => 'Foo']), score: 0.5);
    $action->attachRelated(Tag::create(['name' => 'Bar']), score: 1.0);

    $ordered = Relatable::query()
        ->select('relatables.*')
        ->leftJoin('reviews', 'reviews.relatable_id', '=', 'relatables.id')
        ->minWeight(0.5)
        ->orderByWeight()
        ->get();

    expect($ordered->models()->pluck('name')->all())->toBe(['Bar', 'Foo']);
});
