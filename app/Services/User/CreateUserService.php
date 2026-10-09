<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Enums\LeaveType;
use App\Models\LeaveBalance;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateUserService
{
    /**
     * @param  array{name: string, email: string, password: string, role: string, tenant_id?: int|null}  $data
     */
    public function execute(array $data, int $tenantId): User
    {
        return DB::transaction(function () use ($data, $tenantId): User {
            $user = User::create([
                'tenant_id' => $data['tenant_id'] ?? $tenantId,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $user->assignRole($data['role']);

            // Initialize default leave balances for employee
            $defaultBalances = [
                LeaveType::Casual->value => 12.0,
                LeaveType::Sick->value => 10.0,
                LeaveType::Annual->value => 15.0,
                LeaveType::Unpaid->value => 30.0,
            ];

            foreach ($defaultBalances as $type => $days) {
                LeaveBalance::create([
                    'tenant_id' => $user->tenant_id,
                    'user_id' => $user->id,
                    'leave_type' => $type,
                    'total_days' => $days,
                    'used_days' => 0.0,
                ]);
            }

            return $user->fresh(['roles', 'leaveBalances']);
        });
    }
}
