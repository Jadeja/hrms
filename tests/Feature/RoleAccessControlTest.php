<?php

declare(strict_types=1);

use App\Models\LeaveRequest;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);

    $this->tenant = Tenant::factory()->create();

    $this->employee = User::factory()->forTenant($this->tenant)->asEmployee()->create();
    $this->hrAdmin = User::factory()->forTenant($this->tenant)->asHrAdmin()->create();
    $this->systemAdmin = User::factory()->asSystemAdmin()->create(['tenant_id' => null]);
});

test('employee cannot access user management endpoints', function (): void {
    $response = $this->actingAs($this->employee, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenant->id)
        ->getJson('/api/v1/users');

    $response->assertForbidden();
});

test('employee cannot create user in tenant', function (): void {
    $response = $this->actingAs($this->employee, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenant->id)
        ->postJson('/api/v1/users', [
            'name' => 'John Doe',
            'email' => 'john@test.com',
            'password' => 'password123',
            'role' => 'Employee',
        ]);

    $response->assertForbidden();
});

test('employee cannot approve leave requests', function (): void {
    $leaveRequest = LeaveRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->employee->id,
    ]);

    $response = $this->actingAs($this->employee, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenant->id)
        ->postJson("/api/v1/leaves/requests/{$leaveRequest->id}/approve");

    $response->assertForbidden();
});

test('employee cannot generate attendance reports', function (): void {
    $response = $this->actingAs($this->employee, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenant->id)
        ->postJson('/api/v1/attendance/reports/generate', [
            'month' => 10,
            'year' => 2026,
        ]);

    $response->assertForbidden();
});

test('employee cannot create company holidays', function (): void {
    $response = $this->actingAs($this->employee, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenant->id)
        ->postJson('/api/v1/holidays', [
            'name' => 'New Year',
            'date' => '2027-01-01',
        ]);

    $response->assertForbidden();
});

test('hr admin cannot create new tenants', function (): void {
    $response = $this->actingAs($this->hrAdmin, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenant->id)
        ->postJson('/api/v1/admin/tenants', [
            'name' => 'New Corp',
            'domain' => 'newcorp.hrms.test',
        ]);

    $response->assertForbidden();
});

test('hr admin can create user and initialize leave balances', function (): void {
    $response = $this->actingAs($this->hrAdmin, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenant->id)
        ->postJson('/api/v1/users', [
            'name' => 'Jane Employee',
            'email' => 'jane@hrms.test',
            'password' => 'password123',
            'role' => 'Employee',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.attributes.email', 'jane@hrms.test')
        ->assertJsonPath('data.attributes.roles', ['Employee']);

    $createdUser = User::where('email', 'jane@hrms.test')->first();
    expect($createdUser)->not->toBeNull()
        ->and($createdUser->leaveBalances()->count())->toBe(4);
});

test('system admin can create new tenant', function (): void {
    $response = $this->actingAs($this->systemAdmin, 'sanctum')
        ->postJson('/api/v1/admin/tenants', [
            'name' => 'Global Corp',
            'domain' => 'global.hrms.test',
            'timezone' => 'America/New_York',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.attributes.name', 'Global Corp')
        ->assertJsonPath('data.attributes.domain', 'global.hrms.test');

    expect(Tenant::where('domain', 'global.hrms.test')->exists())->toBeTrue();
});
