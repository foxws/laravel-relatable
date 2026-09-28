<?php

declare(strict_types=1);

namespace Foxws\Relatable\Tests\Fixtures\Models;

use Foxws\Relatable\Models\Relatable;

class LegacyRelated extends Relatable
{
    protected $table = 'related';
}
