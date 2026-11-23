<?php

declare(strict_types=1);

use Foxws\Relatable\Models\Relatable;
use Foxws\Relatable\Tests\Fixtures\Models\Post;
use Foxws\Relatable\Tests\Fixtures\Models\StringableKeyTag;
use Foxws\Relatable\Tests\Fixtures\Models\Tag;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

it('loads the existing relations once when syncing', function () {
    $action = Tag::create(['name' => 'Action']);
    $tags = collect(['Foo', 'Bar', 'Baz'])->map(fn (string $name): Tag => Tag::create(['name' => $name]));

    $action->syncRelated([$tags[0]]);

    DB::enableQueryLog();

    $action->syncRelated($tags->all());

    $selects = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_starts_with(strtolower($query['query']), 'select'));

    expect($selects)->toHaveCount(1);

    $this->assertDatabaseCount('relatables', 3);
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

function useSecondConnection(): void
{
    config()->set('database.connections.second', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);

    $schema = Schema::connection('second');

    $schema->create('tags', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    $schema->create('relatables', function (Blueprint $table) {
        $table->id();
        $table->morphs('relatable');
        $table->morphs('related');
        $table->float('score')->default(1);
        $table->float('boost')->default(1);
        $table->json('options')->nullable();
        $table->timestamps();
    });
}

it('writes relations on the connection of the model', function () {
    useSecondConnection();

    $action = Tag::on('second')->create(['name' => 'Action']);
    $action->attachRelated(Tag::on('second')->create(['name' => 'Foo']), mutual: true);

    expect(DB::connection('second')->table('relatables')->count())->toBe(2);

    $this->assertDatabaseCount('relatables', 0);
});

it('only detaches relations on the connection of the model', function () {
    useSecondConnection();

    Tag::create(['name' => 'Action'])->attachRelated(Tag::create(['name' => 'Foo']));

    $action = Tag::on('second')->create(['name' => 'Action']);
    $foo = Tag::on('second')->create(['name' => 'Foo']);

    expect($action->detachRelated($foo, mutual: true))->toBeFalse();

    $this->assertDatabaseCount('relatables', 1);
});

it('keeps the relations when deleting the model is cancelled', function () {
    $action = Tag::create(['name' => 'Action']);
    $action->attachRelated(Tag::create(['name' => 'Foo']));

    Tag::deleting(fn (): bool => false);

    expect($action->delete())->toBeFalse();

    $this->assertDatabaseCount('relatables', 1);
});

it('keeps the relations when deleting the model fails', function () {
    $action = Tag::create(['name' => 'Action']);
    $action->attachRelated(Tag::create(['name' => 'Foo']));

    DB::statement("create trigger keep_tags before delete on tags begin select raise(abort, 'kept'); end");

    expect(fn () => $action->delete())->toThrow(QueryException::class);

    $this->assertDatabaseCount('relatables', 1);
});

it('detaches the other relations when deleting one is cancelled', function () {
    $action = Tag::create(['name' => 'Action']);
    $foo = Tag::create(['name' => 'Foo']);

    $action->attachRelated($foo, mutual: true);

    Relatable::deleting(fn (Relatable $relatable): ?bool => $relatable->relatable_id === $action->getKey() ? false : null);

    expect($action->detachRelated($foo, mutual: true))->toBeTrue()
        ->and(Relatable::query()->sole()->relatable_id)->toBe($action->getKey());
});

it('does not add the relations to the array when reading related models', function () {
    $action = Tag::create(['name' => 'Action']);
    $action->attachRelated(Post::create(['title' => 'Chase']), options: ['source' => 'manual']);

    $action->getRelates(Tag::class);

    expect($action->toArray())->not->toHaveKey('relatables')
        ->and($action->append('relates')->toArray())->not->toHaveKey('relatables');
});

it('syncs a model with itself when mutual', function () {
    $action = Tag::create(['name' => 'Action']);

    $action->syncRelated([$action], mutual: true);

    $this->assertDatabaseCount('relatables', 1);
});

it('relates a model that was related at the same time elsewhere', function () {
    $action = Tag::create(['name' => 'Action']);
    $foo = Tag::create(['name' => 'Foo']);

    // Insert the same relation right after the lookup for an existing one.
    $inserted = false;

    DB::listen(function (QueryExecuted $query) use (&$inserted, $action, $foo): void {
        if ($inserted || ! str_starts_with($query->sql, 'select')) {
            return;
        }

        $inserted = true;

        DB::table('relatables')->insert([
            'relatable_type' => $action->getMorphClass(),
            'relatable_id' => $action->getKey(),
            'related_type' => $foo->getMorphClass(),
            'related_id' => $foo->getKey(),
        ]);
    });

    $relatable = $action->attachRelated($foo, score: 0.5);

    expect($relatable->score)->toBe(0.5);

    $this->assertDatabaseCount('relatables', 1);
});

it('rejects a score or boost that is not finite', function (array $item) {
    $action = Tag::create(['name' => 'Action']);

    expect(fn () => $action->syncRelated([['model' => Tag::create(['name' => 'Foo']), ...$item]]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $action->attachRelated(Tag::create(['name' => 'Bar']), score: INF))->toThrow(InvalidArgumentException::class);

    $this->assertDatabaseCount('relatables', 0);
})->with([
    'score' => [['score' => '1e999']],
    'boost' => [['boost' => '-1e999']],
]);

it('rejects a score that is not a number when syncing', function () {
    Tag::create(['name' => 'Action'])->syncRelated([['model' => Tag::create(['name' => 'Foo']), 'score' => 'high']]);
})->throws(InvalidArgumentException::class);

it('uses integer defaults from the config', function () {
    config()->set('relatable.defaults.score', 2);

    $relatable = Tag::create(['name' => 'Action'])->attachRelated(Tag::create(['name' => 'Foo']));

    expect($relatable->score)->toBe(2.0);
});

it('syncs models with stringable keys', function () {
    $action = Tag::create(['name' => 'Action']);

    $action->syncRelated([
        StringableKeyTag::create(['name' => 'Foo']),
        StringableKeyTag::create(['name' => 'Bar']),
    ]);

    $this->assertDatabaseCount('relatables', 2);
});

it('refreshes the relations from other models after a mutual change', function () {
    $action = Tag::create(['name' => 'Action']);
    $action->load('relatablesFrom');

    $action->attachRelated(Tag::create(['name' => 'Foo']), mutual: true);

    expect($action->relatablesFrom)->toHaveCount(1);
});
