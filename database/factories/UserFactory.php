<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RoleEnum;
use App\Models\Tenant;
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
    protected static ?string $password = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
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

    public function forTenant(Tenant|TenantFactory $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant instanceof TenantFactory ? $tenant : $tenant->id,
        ]);
    }

    public function asSystemAdmin(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->assignRole(RoleEnum::SystemAdmin->value);
        });
    }

    public function asHrAdmin(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->assignRole(RoleEnum::HrAdmin->value);
        });
    }

    public function asEmployee(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->assignRole(RoleEnum::Employee->value);
        });
    }
}
