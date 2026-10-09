<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read User $resource
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'type' => 'users',
            'attributes' => [
                'tenant_id' => $this->resource->tenant_id,
                'name' => $this->resource->name,
                'email' => $this->resource->email,
                'roles' => $this->resource->roles->pluck('name')->all(),
                'created_at' => $this->resource->created_at?->toISOString(),
                'updated_at' => $this->resource->updated_at?->toISOString(),
            ],
            'relationships' => [
                'tenant' => [
                    'data' => $this->resource->tenant_id !== null ? [
                        'type' => 'tenants',
                        'id' => (string) $this->resource->tenant_id,
                    ] : null,
                ],
            ],
        ];
    }
}
