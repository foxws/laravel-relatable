<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('keeps its index names within the length MySQL allows', function () {
    $names = collect(Schema::getIndexes('relatables'))->pluck('name');

    expect($names)->toContain('relatables_relation_unique')
        ->and($names->filter(fn (string $name): bool => strlen($name) > 64))->toBeEmpty();
});
