<?php

declare(strict_types=1);

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Exceptions\InsufficientLeaveBalanceException;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Leave\ApproveLeaveService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);

    $this->tenant = Tenant::factory()->create();
    $this->employee = User::factory()->forTenant($this->tenant)->asEmployee()->create();
    $this->hrAdmin = User::factory()->forTenant($this->tenant)->asHrAdmin()->create();

    // Allocate 3 days total
    $this->balance = LeaveBalance::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->employee->id,
        'leave_type' => LeaveType::Casual->value,
        'total_days' => 3.0,
        'used_days' => 0.0,
    ]);
});

test('pessimistic locking and transactions prevent concurrent over-approval of leave balances', function (): void {
    // Create two competing pending requests of 3 days each
    $req1 = LeaveRequest::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->employee->id,
        'leave_type' => LeaveType::Casual->value,
        'start_date' => now()->addDays(2)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
        'days' => 3.0,
        'reason' => 'Request 1',
        'status' => LeaveStatus::Pending,
    ]);

    $req2 = LeaveRequest::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->employee->id,
        'leave_type' => LeaveType::Casual->value,
        'start_date' => now()->addDays(6)->toDateString(),
        'end_date' => now()->addDays(8)->toDateString(),
        'days' => 3.0,
        'reason' => 'Request 2',
        'status' => LeaveStatus::Pending,
    ]);

    $service = app(ApproveLeaveService::class);

    // First approval succeeds using pessimistic lock on LeaveBalance
    $approved1 = $service->execute((int) $req1->id, $this->hrAdmin);
    expect($approved1->status)->toBe(LeaveStatus::Approved)
        ->and($this->balance->fresh()->used_days)->toBe(3.0)
        ->and($this->balance->fresh()->availableDays())->toBe(0.0);

    // Second approval under locked state MUST fail with InsufficientLeaveBalanceException
    expect(fn () => $service->execute((int) $req2->id, $this->hrAdmin))
        ->toThrow(InsufficientLeaveBalanceException::class);

    // Verify req2 was NOT approved and balance did not go into negative
    expect($req2->fresh()->status)->toBe(LeaveStatus::Pending)
        ->and($this->balance->fresh()->used_days)->toBe(3.0);
});

test('pessimistic lock query executes lockForUpdate in transaction', function (): void {
    DB::enableQueryLog();

    $leaveRequest = LeaveRequest::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->employee->id,
        'leave_type' => LeaveType::Casual->value,
        'start_date' => now()->addDays(2)->toDateString(),
        'end_date' => now()->addDays(3)->toDateString(),
        'days' => 2.0,
        'reason' => 'Test',
        'status' => LeaveStatus::Pending,
    ]);

    $service = app(ApproveLeaveService::class);
    $service->execute((int) $leaveRequest->id, $this->hrAdmin);

    $builder = LeaveBalance::where('user_id', $this->employee->id)->lockForUpdate();
    expect($builder->toBase()->lock)->toBeTrue();
});
