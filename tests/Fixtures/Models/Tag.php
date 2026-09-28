<?php

declare(strict_types=1);

namespace Foxws\Relatable\Tests\Fixtures\Models;

use Foxws\Relatable\Concerns\InteractsWithRelated;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use InteractsWithRelated;

    protected $guarded = [];
}
