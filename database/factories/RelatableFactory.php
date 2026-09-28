<?php

declare(strict_types=1);

namespace Foxws\Relatable\Database\Factories;

use Foxws\Relatable\Models\Relatable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Relatable>
 */
class RelatableFactory extends Factory
{
    protected $model = Relatable::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'score' => $this->faker->randomFloat(2, 0, 1),
            'boost' => 1.0,
            'options' => null,
        ];
    }

    public function between(Model $relatable, Model $related): static
    {
        return $this->state(fn (array $attributes): array => [
            'relatable_type' => $relatable->getMorphClass(),
            'relatable_id' => $relatable->getKey(),
            'related_type' => $related->getMorphClass(),
            'related_id' => $related->getKey(),
        ]);
    }
}
