<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'phone' => '07'.fake()->randomElement(['7', '8', '0', '5']).fake()->numerify('#######'),
            'city' => fake()->randomElement(['Lira', 'Kampala', 'Gulu', 'Mbarara', 'Jinja', 'Entebbe', 'Mbale', 'Arua']),
            'gender' => fake()->randomElement(['male', 'female']),
            'date_of_birth' => fake()->dateTimeBetween('-60 years', '-16 years'),
            'terms_accepted_at' => now(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
