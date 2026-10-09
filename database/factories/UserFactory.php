<?php

namespace Database\Factories;

use App\Enums\UserRole;
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
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Superadmin,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function superadmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Superadmin,
        ]);
    }

    public function projectAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::ProjectAdmin,
        ]);
    }

    public function boqCostControl(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::BoqCostControl,
        ]);
    }

    public function approver(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Approver,
        ]);
    }

    public function finance(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Finance,
        ]);
    }

    public function projectSite(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::ProjectSite,
        ]);
    }

    public function role(UserRole $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => $role,
        ]);
    }
}
