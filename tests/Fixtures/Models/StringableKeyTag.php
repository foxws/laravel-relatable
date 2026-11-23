<?php

declare(strict_types=1);

namespace Foxws\Relatable\Tests\Fixtures\Models;

use Illuminate\Support\Stringable;

/**
 * Tag whose key is a Stringable, like a value object key cast.
 */
class StringableKeyTag extends Tag
{
    protected $table = 'tags';

    public function getKey(): ?Stringable
    {
        $key = $this->getAttribute($this->getKeyName());

        return $key === null ? null : new Stringable((string) $key);
    }
}
