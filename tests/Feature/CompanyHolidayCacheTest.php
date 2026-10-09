<?php

declare(strict_types=1);

use App\Models\CompanyHoliday;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Holiday\CompanyHolidayService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    Cache::flush();

    $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
    $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B']);

    $this->hrA = User::factory()->forTenant($this->tenantA)->asHrAdmin()->create();
    $this->userA = User::factory()->forTenant($this->tenantA)->asEmployee()->create();
});

test('holidays are cached per tenant on read', function (): void {
    CompanyHoliday::create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'New Year Day',
        'date' => '2027-01-01',
    ]);

    $cacheKey = "tenant:{$this->tenantA->id}:holidays:all";
    expect(Cache::has($cacheKey))->toBeFalse();

    $response = $this->actingAs($this->userA, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenantA->id)
        ->getJson('/api/v1/holidays');

    $response->assertOk()
        ->assertJsonPath('data.0.attributes.name', 'New Year Day');

    // Cache is populated
    expect(Cache::has($cacheKey))->toBeTrue();
    $cached = Cache::get($cacheKey);
    expect($cached->first()->name)->toBe('New Year Day');
});

test('creating holiday invalidates tenant cache', function (): void {
    CompanyHoliday::create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Labor Day',
        'date' => '2027-05-01',
    ]);

    $cacheKey = "tenant:{$this->tenantA->id}:holidays:all";

    // Prime the cache
    $this->actingAs($this->userA, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenantA->id)
        ->getJson('/api/v1/holidays');

    expect(Cache::has($cacheKey))->toBeTrue();

    // HR Admin creates a new holiday
    $response = $this->actingAs($this->hrA, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenantA->id)
        ->postJson('/api/v1/holidays', [
            'name' => 'Independence Day',
            'date' => '2027-07-04',
        ]);

    $response->assertCreated();

    // Cache must be invalidated
    expect(Cache::has($cacheKey))->toBeFalse();

    // Next get repopulates cache with updated list
    $freshResponse = $this->actingAs($this->userA, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenantA->id)
        ->getJson('/api/v1/holidays');

    $freshResponse->assertOk();
    expect($freshResponse->json('data'))->toHaveCount(2);
});

test('deleting holiday invalidates tenant cache', function (): void {
    $holiday = CompanyHoliday::create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Holiday To Delete',
        'date' => '2027-12-25',
    ]);

    $cacheKey = "tenant:{$this->tenantA->id}:holidays:all";

    // Prime the cache
    $this->actingAs($this->userA, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenantA->id)
        ->getJson('/api/v1/holidays');

    expect(Cache::has($cacheKey))->toBeTrue();

    // HR Admin deletes holiday
    $response = $this->actingAs($this->hrA, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenantA->id)
        ->deleteJson("/api/v1/holidays/{$holiday->id}");

    $response->assertNoContent();

    // Cache must be invalidated
    expect(Cache::has($cacheKey))->toBeFalse();
});

test('tenant A cache invalidation does not affect tenant B cache', function (): void {
    CompanyHoliday::create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Holiday A',
        'date' => '2027-01-01',
    ]);

    CompanyHoliday::create([
        'tenant_id' => $this->tenantB->id,
        'name' => 'Holiday B',
        'date' => '2027-02-01',
    ]);

    $keyA = "tenant:{$this->tenantA->id}:holidays:all";
    $keyB = "tenant:{$this->tenantB->id}:holidays:all";

    // Prime both caches
    $service = app(CompanyHolidayService::class);
    $service->getHolidays($this->tenantA->id);
    $service->getHolidays($this->tenantB->id);

    expect(Cache::has($keyA))->toBeTrue()
        ->and(Cache::has($keyB))->toBeTrue();

    // Invalidate Tenant A
    $service->invalidateTenantCache($this->tenantA->id);

    expect(Cache::has($keyA))->toBeFalse()
        ->and(Cache::has($keyB))->toBeTrue();
});
