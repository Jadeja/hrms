<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AttendanceStatus;
use App\Enums\ReportStatus;
use App\Models\Attendance;
use App\Models\MonthlyAttendanceReport;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateMonthlyAttendanceReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     * Exponential backoff: 10s, 30s, 60s.
     *
     * @var list<int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly int $reportId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        /** @var MonthlyAttendanceReport|null $report */
        $report = MonthlyAttendanceReport::find($this->reportId);

        if ($report === null) {
            return;
        }

        $report->update(['status' => ReportStatus::Processing]);

        // Make tenant current to ensure proper scoping
        $tenant = Tenant::find($report->tenant_id);
        $tenant?->makeCurrent();

        $month = $report->month;
        $year = $report->year;

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $employees = User::where('tenant_id', $report->tenant_id)->get();

        $attendances = Attendance::where('tenant_id', $report->tenant_id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        $employeeStats = [];
        $totalPresent = 0;
        $totalLate = 0;
        $totalHalfDay = 0;

        foreach ($employees as $employee) {
            $userAttendances = $attendances->where('user_id', $employee->id);
            $presentCount = $userAttendances->where('status', AttendanceStatus::Present)->count();
            $lateCount = $userAttendances->where('status', AttendanceStatus::Late)->count();
            $halfDayCount = $userAttendances->where('status', AttendanceStatus::HalfDay)->count();
            $absentCount = $userAttendances->where('status', AttendanceStatus::Absent)->count();

            $totalPresent += $presentCount;
            $totalLate += $lateCount;
            $totalHalfDay += $halfDayCount;

            $employeeStats[] = [
                'user_id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'present' => $presentCount,
                'late' => $lateCount,
                'half_day' => $halfDayCount,
                'absent' => $absentCount,
                'total_recorded_days' => $userAttendances->count(),
            ];
        }

        $reportData = [
            'period' => [
                'month' => $month,
                'year' => $year,
                'days_in_month' => $startDate->daysInMonth,
            ],
            'summary' => [
                'total_employees' => $employees->count(),
                'total_present_records' => $totalPresent,
                'total_late_records' => $totalLate,
                'total_half_day_records' => $totalHalfDay,
            ],
            'employee_breakdown' => $employeeStats,
            'generated_at' => now()->toISOString(),
        ];

        $report->update([
            'status' => ReportStatus::Completed,
            'data' => $reportData,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        $report = MonthlyAttendanceReport::find($this->reportId);
        $report?->update([
            'status' => ReportStatus::Failed,
            'data' => [
                'error' => $exception?->getMessage() ?? 'Report generation failed.',
            ],
        ]);
    }
}
