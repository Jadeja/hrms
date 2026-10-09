<?php

declare(strict_types=1);

namespace App\Services\Holiday;

use App\Models\CompanyHoliday;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

final readonly class CompanyHolidayService
{
    private const int TTL_SECONDS = 86400; // 24 hours

    /**
     * @return Collection<int, CompanyHoliday>
     */
    public function getHolidays(int $tenantId, ?int $year = null): Collection
    {
        $cacheKey = $this->buildCacheKey($tenantId, $year);

        // Keep track of active cache keys for this tenant for complete invalidation
        $this->registerCacheKey($tenantId, $cacheKey);

        return Cache::remember($cacheKey, self::TTL_SECONDS, function () use ($tenantId, $year): Collection {
            $query = CompanyHoliday::where('tenant_id', $tenantId);

            if ($year !== null) {
                $query->whereYear('date', $year);
            }

            return $query->orderBy('date')->get();
        });
    }

    /**
     * @param  array{name: string, date: string, is_recurring?: bool}  $data
     */
    public function createHoliday(int $tenantId, array $data): CompanyHoliday
    {
        $holiday = CompanyHoliday::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'date' => $data['date'],
            'is_recurring' => $data['is_recurring'] ?? false,
        ]);

        $this->invalidateTenantCache($tenantId);

        return $holiday;
    }

    public function deleteHoliday(CompanyHoliday $holiday): void
    {
        $tenantId = (int) $holiday->tenant_id;
        $holiday->delete();

        $this->invalidateTenantCache($tenantId);
    }

    public function invalidateTenantCache(int $tenantId): void
    {
        $registryKey = "tenant:{$tenantId}:holidays:tracked_keys";
        /** @var list<string> $trackedKeys */
        $trackedKeys = Cache::get($registryKey, []);

        foreach ($trackedKeys as $key) {
            Cache::forget($key);
        }

        Cache::forget("tenant:{$tenantId}:holidays:all");
        Cache::forget($registryKey);
    }

    private function buildCacheKey(int $tenantId, ?int $year): string
    {
        return "tenant:{$tenantId}:holidays:".($year !== null ? (string) $year : 'all');
    }

    private function registerCacheKey(int $tenantId, string $key): void
    {
        $registryKey = "tenant:{$tenantId}:holidays:tracked_keys";
        /** @var list<string> $trackedKeys */
        $trackedKeys = Cache::get($registryKey, []);

        if (! in_array($key, $trackedKeys, true)) {
            $trackedKeys[] = $key;
            Cache::put($registryKey, $trackedKeys, self::TTL_SECONDS);
        }
    }
}
