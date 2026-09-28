<?php

declare(strict_types=1);

namespace Foxws\Relatable\Models;

use ArrayObject;
use Foxws\Relatable\Collections\RelatableCollection;
use Foxws\Relatable\Database\Factories\RelatableFactory;
use Foxws\Relatable\QueryBuilders\RelatableQueryBuilder;
use Illuminate\Database\Eloquent\Attributes\CollectedBy;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A directed relation from one model (relatable) to another (related).
 *
 * @property int $id
 * @property string $relatable_type
 * @property int|string $relatable_id
 * @property string $related_type
 * @property int|string $related_id
 * @property float $score
 * @property float $boost
 * @property-read float $weight
 * @property ArrayObject<array-key, mixed>|null $options
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $relatable
 * @property-read Model|null $related
 */
#[CollectedBy(RelatableCollection::class)]
#[UseEloquentBuilder(RelatableQueryBuilder::class)]
class Relatable extends Model
{
    /** @use HasFactory<RelatableFactory> */
    use HasFactory;

    protected $table = 'relatables';

    /**
     * Also set as properties: unlike the class attributes, these are
     * inherited by a subclass on older Laravel versions.
     */
    protected static string $builder = RelatableQueryBuilder::class;

    protected static string $collectionClass = RelatableCollection::class;

    /** @var list<string> */
    protected $fillable = [
        'relatable_type',
        'relatable_id',
        'related_type',
        'related_id',
        'score',
        'boost',
        'options',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'float',
            'boost' => 'float',
            'options' => AsArrayObject::class,
        ];
    }

    /**
     * The model this relation starts from.
     *
     * @return MorphTo<Model, $this>
     */
    public function relatable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The model this relation points to.
     *
     * @return MorphTo<Model, $this>
     */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The priority of this relation: its score multiplied by its boost.
     *
     * @return Attribute<float, never>
     */
    protected function weight(): Attribute
    {
        return Attribute::get(fn (): float => $this->score * $this->boost);
    }

    protected static function newFactory(): RelatableFactory
    {
        return RelatableFactory::new();
    }

    /**
     * @return class-string<Relatable>
     */
    public static function modelClass(): string
    {
        return config('relatable.models.relatable', static::class);
    }
}
