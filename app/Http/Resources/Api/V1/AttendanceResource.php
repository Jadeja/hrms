<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Attendance $resource
 */
class AttendanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'type' => 'attendances',
            'attributes' => [
                'tenant_id' => $this->resource->tenant_id,
                'user_id' => $this->resource->user_id,
                'date' => $this->resource->date->toDateString(),
                'check_in_at' => $this->resource->check_in_at?->toISOString(),
                'check_out_at' => $this->resource->check_out_at?->toISOString(),
                'status' => $this->resource->status->value,
                'notes' => $this->resource->notes,
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
            ],
        ];
    }
}
