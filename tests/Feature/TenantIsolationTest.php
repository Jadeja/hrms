<?php

declare(strict_types=1);

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);

    $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
    $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B']);

    $this->userA = User::factory()->forTenant($this->tenantA)->asEmployee()->create();
    $this->userB = User::factory()->forTenant($this->tenantB)->asEmployee()->create();

    $this->hrA = User::factory()->forTenant($this->tenantA)->asHrAdmin()->create();
    $this->hrB = User::factory()->forTenant($this->tenantB)->asHrAdmin()->create();
});

test('user from tenant A cannot use tenant B header', function (): void {
    $response = $this->actingAs($this->userA, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenantB->id)
        ->getJson('/api/v1/leaves/balances');

    $response->assertForbidden()
        ->assertJson(['message' => 'Tenant access denied: Token does not belong to requested tenant.']);
});

test('hr admin in tenant A cannot see attendance records of tenant B', function (): void {
    // Attendance in Tenant A
    Attendance::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'user_id' => $this->userA->id,
        'date' => now()->toDateString(),
    ]);

    // Attendance in Tenant B
    Attendance::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'user_id' => $this->userB->id,
        'date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($this->hrA, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenantA->id)
        ->getJson('/api/v1/attendance');

    $response->assertOk();
    $data = $response->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['attributes']['tenant_id'])->toBe($this->tenantA->id)
        ->and($data[0]['attributes']['user_id'])->toBe($this->userA->id);
});

test('hr admin in tenant A cannot see leave requests of tenant B', function (): void {
    LeaveRequest::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'user_id' => $this->userA->id,
    ]);

    LeaveRequest::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'user_id' => $this->userB->id,
    ]);

    $response = $this->actingAs($this->hrA, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenantA->id)
        ->getJson('/api/v1/leaves/requests');

    $response->assertOk();
    $data = $response->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['attributes']['tenant_id'])->toBe($this->tenantA->id)
        ->and($data[0]['attributes']['user_id'])->toBe($this->userA->id);
});

test('system admin can switch context to any tenant using X-Tenant-ID', function (): void {
    $systemAdmin = User::factory()->asSystemAdmin()->create(['tenant_id' => null]);

    $response = $this->actingAs($systemAdmin, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenantB->id)
        ->getJson('/api/v1/users');

    $response->assertOk();
    $emails = collect($response->json('data'))->pluck('attributes.email')->all();

    expect($emails)->toContain($this->userB->email)
        ->and($emails)->not->toContain($this->userA->email);
});
