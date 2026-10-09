<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read LeaveRequest $resource
 */
class LeaveRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'type' => 'leave_requests',
            'attributes' => [
                'tenant_id' => $this->resource->tenant_id,
                'user_id' => $this->resource->user_id,
                'leave_type' => $this->resource->leave_type->value,
                'start_date' => $this->resource->start_date->toDateString(),
                'end_date' => $this->resource->end_date->toDateString(),
                'days' => $this->resource->days,
                'reason' => $this->resource->reason,
                'status' => $this->resource->status->value,
                'approved_by' => $this->resource->approved_by,
                'rejection_reason' => $this->resource->rejection_reason,
                'created_at' => $this->resource->created_at?->toISOString(),
                'updated_at' => $this->resource->updated_at?->toISOString(),
            ],
            'relationships' => [
                'user' => [
                    'data' => [
                        'type' => 'users',
                        'id' => (string) $this->resource->user_id,
                    ],
                ],
                'approver' => [
                    'data' => $this->resource->approved_by !== null ? [
                        'type' => 'users',
                        'id' => (string) $this->resource->approved_by,
                    ] : null,
                ],
            ],
        ];
    }
}
