<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\MonthlyAttendanceReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read MonthlyAttendanceReport $resource
 */
class MonthlyAttendanceReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'type' => 'monthly_attendance_reports',
            'attributes' => [
                'tenant_id' => $this->resource->tenant_id,
                'month' => $this->resource->month,
                'year' => $this->resource->year,
                'status' => $this->resource->status->value,
                'data' => $this->resource->data,
                'generated_by' => $this->resource->generated_by,
                'created_at' => $this->resource->created_at?->toISOString(),
                'updated_at' => $this->resource->updated_at?->toISOString(),
            ],
        ];
    }
}
