<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CompanyHoliday;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyHoliday>
 */
class CompanyHolidayFactory extends Factory
{
    protected $model = CompanyHoliday::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(2, true).' Day',
            'date' => fake()->date(),
            'is_recurring' => false,
        ];
    }
}
