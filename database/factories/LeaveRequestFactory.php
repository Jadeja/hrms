<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\LeaveRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = now()->addDays(2)->toDateString();
        $endDate = now()->addDays(4)->toDateString();

        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => fn (array $attributes) => User::factory()->state(['tenant_id' => $attributes['tenant_id']]),
            'leave_type' => fake()->randomElement(LeaveType::cases()),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'days' => 3.0,
            'reason' => fake()->sentence(),
            'status' => LeaveStatus::Pending,
            'approved_by' => null,
            'rejection_reason' => null,
        ];
    }
}
