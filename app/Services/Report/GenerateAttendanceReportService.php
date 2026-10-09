<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Enums\ReportStatus;
use App\Jobs\GenerateMonthlyAttendanceReportJob;
use App\Models\MonthlyAttendanceReport;
use App\Models\User;

final readonly class GenerateAttendanceReportService
{
    public function execute(int $tenantId, int $month, int $year, ?User $generator = null): MonthlyAttendanceReport
    {
        $report = MonthlyAttendanceReport::create([
            'tenant_id' => $tenantId,
            'month' => $month,
            'year' => $year,
            'status' => ReportStatus::Pending,
            'generated_by' => $generator?->id,
        ]);

        GenerateMonthlyAttendanceReportJob::dispatch($report->id);

        return $report;
    }
}
