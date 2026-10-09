<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);

    $this->tenant = Tenant::factory()->create();
    $this->employee = User::factory()->forTenant($this->tenant)->asEmployee()->create();
    $this->hrAdmin = User::factory()->forTenant($this->tenant)->asHrAdmin()->create();
});

test('employee can check in successfully', function (): void {
    Carbon::setTestNow('2026-10-09 09:00:00');

    $response = $this->actingAs($this->employee, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenant->id)
        ->postJson('/api/v1/attendance/check-in', [
            'notes' => 'On time',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.attributes.status', AttendanceStatus::Present->value)
        ->assertJsonPath('data.attributes.notes', 'On time');

    expect(Attendance::where('user_id', $this->employee->id)->count())->toBe(1);
});

test('employee check in is marked late after 9:15 AM', function (): void {
    Carbon::setTestNow('2026-10-09 09:30:00');

    $response = $this->actingAs($this->employee, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenant->id)
        ->postJson('/api/v1/attendance/check-in');

    $response->assertCreated()
        ->assertJsonPath('data.attributes.status', AttendanceStatus::Late->value);
});

test('employee cannot check in twice on the same day', function (): void {
    Carbon::setTestNow('2026-10-09 09:00:00');

    Attendance::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->employee->id,
        'date' => '2026-10-09',
        'check_in_at' => now(),
        'status' => AttendanceStatus::Present,
    ]);

    $response = $this->actingAs($this->employee, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenant->id)
        ->postJson('/api/v1/attendance/check-in');

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['attendance']);
});

test('employee can check out after checking in', function (): void {
    Carbon::setTestNow('2026-10-09 09:00:00');

    Attendance::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->employee->id,
        'date' => '2026-10-09',
        'check_in_at' => now(),
        'status' => AttendanceStatus::Present,
    ]);

    Carbon::setTestNow('2026-10-09 17:00:00');

    $response = $this->actingAs($this->employee, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenant->id)
        ->postJson('/api/v1/attendance/check-out');

    $response->assertOk()
        ->assertJsonPath('data.attributes.check_out_at', '2026-10-09T17:00:00.000000Z');

    $attendance = Attendance::where('user_id', $this->employee->id)->first();
    expect($attendance->check_out_at)->not->toBeNull();
});

test('employee cannot check out without checking in first', function (): void {
    Carbon::setTestNow('2026-10-09 17:00:00');

    $response = $this->actingAs($this->employee, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenant->id)
        ->postJson('/api/v1/attendance/check-out');

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['attendance']);
});

test('employee can view own attendance records', function (): void {
    Attendance::factory()->count(3)->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->employee->id,
    ]);

    $response = $this->actingAs($this->employee, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenant->id)
        ->getJson('/api/v1/attendance/my-records');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(3);
});

test('hr admin can view all attendance records in tenant', function (): void {
    $otherEmployee = User::factory()->forTenant($this->tenant)->asEmployee()->create();

    Attendance::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->employee->id,
    ]);

    Attendance::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $otherEmployee->id,
    ]);

    $response = $this->actingAs($this->hrAdmin, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenant->id)
        ->getJson('/api/v1/attendance');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});
