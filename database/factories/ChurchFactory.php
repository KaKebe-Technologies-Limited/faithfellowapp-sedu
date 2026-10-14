<?php

namespace Database\Factories;

use App\Models\Church;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Church>
 */
class ChurchFactory extends Factory
{
    protected $model = Church::class;

    public function definition(): array
    {
        $name = fake()->randomElement(['Grace', 'Faith', 'Hope', 'Victory', 'New Life', 'Living Waters', 'Calvary', 'Emmanuel', 'Bethel', 'Zion'])
            .' '.fake()->randomElement(['Community Church', 'Fellowship', 'Chapel', 'Tabernacle', 'Cathedral', 'Gospel Church'])
            .' '.fake()->unique()->numberBetween(1, 99999);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'denomination' => fake()->randomElement(config('faithfellows.denominations')),
            'tagline' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'pastor_name' => 'Rev. '.fake()->name(),
            'languages' => ['en'],
            'country' => 'UG',
            'city' => fake()->randomElement(['Lira', 'Kampala', 'Gulu', 'Mbarara', 'Jinja']),
            'phone' => '0772'.fake()->numerify('######'),
            'status' => 'active',
            'approved_at' => now(),
            'website_blocks' => Church::DEFAULT_BLOCKS,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending', 'approved_at' => null]);
    }
}
