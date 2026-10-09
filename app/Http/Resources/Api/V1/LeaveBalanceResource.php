<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\LeaveBalance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read LeaveBalance $resource
 */
class LeaveBalanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'type' => 'leave_balances',
            'attributes' => [
                'tenant_id' => $this->resource->tenant_id,
                'user_id' => $this->resource->user_id,
                'leave_type' => $this->resource->leave_type->value,
                'total_days' => $this->resource->total_days,
                'used_days' => $this->resource->used_days,
                'available_days' => $this->resource->availableDays(),
                'created_at' => $this->resource->created_at?->toISOString(),
                'updated_at' => $this->resource->updated_at?->toISOString(),
            ],
        ];
    }
}
