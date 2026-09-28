<?php

declare(strict_types=1);

namespace Foxws\Relatable\Tests\Fixtures\Models;

use Foxws\Relatable\Concerns\InteractsWithRelated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use InteractsWithRelated;
    use SoftDeletes;

    protected $guarded = [];
}
