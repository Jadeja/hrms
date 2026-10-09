<?php

declare(strict_types=1);

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);

    $this->tenant = Tenant::factory()->create();
    $this->employee = User::factory()->forTenant($this->tenant)->asEmployee()->create();
    $this->hrAdmin = User::factory()->forTenant($this->tenant)->asHrAdmin()->create();

    // Give employee 10 days of Casual leave
    $this->balance = LeaveBalance::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->employee->id,
        'leave_type' => LeaveType::Casual->value,
        'total_days' => 10.0,
        'used_days' => 0.0,
    ]);
});

test('employee can submit leave request with sufficient balance', function (): void {
    $startDate = now()->addDays(2)->toDateString();
    $endDate = now()->addDays(4)->toDateString();

    $response = $this->actingAs($this->employee, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenant->id)
        ->postJson('/api/v1/leaves/requests', [
            'leave_type' => LeaveType::Casual->value,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason' => 'Family vacation',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.attributes.status', LeaveStatus::Pending->value)
        ->assertJsonPath('data.attributes.days', 3);

    expect(LeaveRequest::where('user_id', $this->employee->id)->count())->toBe(1);
    // Balance not yet deducted while pending
    expect($this->balance->fresh()->used_days)->toBe(0.0);
});

test('employee cannot submit leave request exceeding balance', function (): void {
    $startDate = now()->addDays(2)->toDateString();
    $endDate = now()->addDays(15)->toDateString(); // 14 days requested, only 10 available

    $response = $this->actingAs($this->employee, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenant->id)
        ->postJson('/api/v1/leaves/requests', [
            'leave_type' => LeaveType::Casual->value,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason' => 'Extended leave',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['days']);
});

test('employee cannot submit overlapping leave request', function (): void {
    LeaveRequest::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->employee->id,
        'leave_type' => LeaveType::Casual->value,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(8)->toDateString(),
        'days' => 4.0,
        'reason' => 'First trip',
        'status' => LeaveStatus::Pending,
    ]);

    // Overlapping dates
    $response = $this->actingAs($this->employee, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenant->id)
        ->postJson('/api/v1/leaves/requests', [
            'leave_type' => LeaveType::Casual->value,
            'start_date' => now()->addDays(7)->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'reason' => 'Second trip',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['dates']);
});

test('hr admin can approve leave request and deduct balance', function (): void {
    $leaveRequest = LeaveRequest::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->employee->id,
        'leave_type' => LeaveType::Casual->value,
        'start_date' => now()->addDays(2)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
        'days' => 3.0,
        'reason' => 'Vacation',
        'status' => LeaveStatus::Pending,
    ]);

    $response = $this->actingAs($this->hrAdmin, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenant->id)
        ->postJson("/api/v1/leaves/requests/{$leaveRequest->id}/approve");

    $response->assertOk()
        ->assertJsonPath('data.attributes.status', LeaveStatus::Approved->value)
        ->assertJsonPath('data.attributes.approved_by', $this->hrAdmin->id);

    expect($this->balance->fresh()->used_days)->toBe(3.0)
        ->and($this->balance->fresh()->availableDays())->toBe(7.0);
});

test('hr admin can reject leave request with reason', function (): void {
    $leaveRequest = LeaveRequest::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->employee->id,
        'leave_type' => LeaveType::Casual->value,
        'start_date' => now()->addDays(2)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
        'days' => 3.0,
        'reason' => 'Vacation',
        'status' => LeaveStatus::Pending,
    ]);

    $response = $this->actingAs($this->hrAdmin, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenant->id)
        ->postJson("/api/v1/leaves/requests/{$leaveRequest->id}/reject", [
            'reason' => 'Staff shortage on those dates',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.attributes.status', LeaveStatus::Rejected->value)
        ->assertJsonPath('data.attributes.rejection_reason', 'Staff shortage on those dates');

    // Balance untouched
    expect($this->balance->fresh()->used_days)->toBe(0.0);
});

test('cannot approve non-pending leave request', function (): void {
    $leaveRequest = LeaveRequest::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->employee->id,
        'leave_type' => LeaveType::Casual->value,
        'start_date' => now()->addDays(2)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
        'days' => 3.0,
        'reason' => 'Vacation',
        'status' => LeaveStatus::Approved,
    ]);

    $response = $this->actingAs($this->hrAdmin, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenant->id)
        ->postJson("/api/v1/leaves/requests/{$leaveRequest->id}/approve");

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

test('employee can view own leave balances', function (): void {
    $response = $this->actingAs($this->employee, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenant->id)
        ->getJson('/api/v1/leaves/balances');

    $response->assertOk()
        ->assertJsonPath('data.0.attributes.leave_type', LeaveType::Casual->value)
        ->assertJsonPath('data.0.attributes.total_days', 10)
        ->assertJsonPath('data.0.attributes.available_days', 10);
});
