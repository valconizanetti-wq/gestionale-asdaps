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
            'password' => static::$password ??= Hash::make('password'),
            'attivo' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /** Ruolo predefinito Segretario, se il test non ne sceglie altri con conRuoli(). */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            if (! $user->ruoli()->exists()) {
                $user->assegnaRuoli(['segretario']);
            }
        });
    }

    /** Ruoli dell'utente (sostituiscono il predefinito). */
    public function conRuoli(string ...$codici): static
    {
        return $this->afterCreating(fn (User $user) => $user->assegnaRuoli($codici));
    }
}
