<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LeaveType;
use App\Models\LeaveBalance;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveBalance>
 */
class LeaveBalanceFactory extends Factory
{
    protected $model = LeaveBalance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => fn (array $attributes) => User::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'leave_type' => fake()->randomElement(LeaveType::cases()),
            'total_days' => 15.0,
            'used_days' => 0.0,
        ];
    }
}
