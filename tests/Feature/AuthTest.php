<?php

declare(strict_types=1);

use App\Enums\RoleEnum;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->tenant = Tenant::factory()->create();
});

test('user can log in with valid credentials and receive token', function (): void {
    $user = User::factory()->forTenant($this->tenant)->create([
        'email' => 'employee@hrms.test',
        'password' => Hash::make('password123'),
    ]);
    $user->assignRole(RoleEnum::Employee->value);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'employee@hrms.test',
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'type',
                'attributes' => [
                    'tenant_id',
                    'name',
                    'email',
                    'roles',
                ],
            ],
            'meta' => [
                'token',
                'token_type',
            ],
        ]);
});

test('login fails with invalid credentials', function (): void {
    User::factory()->forTenant($this->tenant)->create([
        'email' => 'employee@hrms.test',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'employee@hrms.test',
        'password' => 'wrong-password',
    ]);

    $response->assertUnauthorized();
});

test('authenticated user can view profile with tenant context', function (): void {
    $user = User::factory()->forTenant($this->tenant)->asEmployee()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenant->id)
        ->getJson('/api/v1/auth/me');

    $response->assertOk()
        ->assertJsonPath('data.attributes.email', $user->email)
        ->assertJsonPath('data.attributes.tenant_id', $this->tenant->id);
});

test('authenticated user can log out and revoke token', function (): void {
    $user = User::factory()->forTenant($this->tenant)->asEmployee()->create();

    $token = $user->createToken('test-token')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->withHeader('X-Tenant-ID', (string) $this->tenant->id)
        ->postJson('/api/v1/auth/logout');

    $response->assertOk()
        ->assertJson(['message' => 'Successfully logged out.']);

    expect($user->fresh()->tokens()->count())->toBe(0);
});
