<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\CompanyHoliday;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read CompanyHoliday $resource
 */
class CompanyHolidayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'type' => 'company_holidays',
            'attributes' => [
                'tenant_id' => $this->resource->tenant_id,
                'name' => $this->resource->name,
                'date' => $this->resource->date->toDateString(),
                'is_recurring' => $this->resource->is_recurring,
                'created_at' => $this->resource->created_at?->toISOString(),
                'updated_at' => $this->resource->updated_at?->toISOString(),
            ],
        ];
    }
}
