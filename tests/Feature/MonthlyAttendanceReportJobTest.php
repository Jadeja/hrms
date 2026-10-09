<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Enums\ReportStatus;
use App\Jobs\GenerateMonthlyAttendanceReportJob;
use App\Models\Attendance;
use App\Models\MonthlyAttendanceReport;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);

    $this->tenant = Tenant::factory()->create();
    $this->hrAdmin = User::factory()->forTenant($this->tenant)->asHrAdmin()->create();
    $this->employee = User::factory()->forTenant($this->tenant)->asEmployee()->create();
});

test('attendance report generation endpoint dispatches job asynchronously and returns 202', function (): void {
    Queue::fake();

    $response = $this->actingAs($this->hrAdmin, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenant->id)
        ->postJson('/api/v1/attendance/reports/generate', [
            'month' => 10,
            'year' => 2026,
        ]);

    $response->assertAccepted()
        ->assertJsonPath('data.attributes.status', ReportStatus::Pending->value)
        ->assertJsonPath('data.attributes.month', 10)
        ->assertJsonPath('data.attributes.year', 2026);

    Queue::assertPushed(GenerateMonthlyAttendanceReportJob::class);
});

test('job has exponential backoff and retry configuration', function (): void {
    $job = new GenerateMonthlyAttendanceReportJob(1);

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([10, 30, 60]);
});

test('job processes monthly attendance records and generates report data', function (): void {
    // Attendance records for October 2026
    Attendance::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->employee->id,
        'date' => '2026-10-01',
        'status' => AttendanceStatus::Present,
    ]);

    Attendance::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->employee->id,
        'date' => '2026-10-02',
        'status' => AttendanceStatus::Late,
    ]);

    $report = MonthlyAttendanceReport::create([
        'tenant_id' => $this->tenant->id,
        'month' => 10,
        'year' => 2026,
        'status' => ReportStatus::Pending,
        'generated_by' => $this->hrAdmin->id,
    ]);

    $job = new GenerateMonthlyAttendanceReportJob($report->id);
    $job->handle();

    $report->refresh();

    expect($report->status)->toBe(ReportStatus::Completed)
        ->and($report->data)->not->toBeNull()
        ->and($report->data['summary']['total_present_records'])->toBe(1)
        ->and($report->data['summary']['total_late_records'])->toBe(1)
        ->and($report->data['period']['month'])->toBe(10)
        ->and($report->data['period']['year'])->toBe(2026);
});

test('hr admin can fetch generated attendance report via API', function (): void {
    $report = MonthlyAttendanceReport::create([
        'tenant_id' => $this->tenant->id,
        'month' => 10,
        'year' => 2026,
        'status' => ReportStatus::Completed,
        'data' => [
            'summary' => ['total_employees' => 2],
        ],
        'generated_by' => $this->hrAdmin->id,
    ]);

    $response = $this->actingAs($this->hrAdmin, 'sanctum')
        ->withHeader('X-Tenant-ID', (string) $this->tenant->id)
        ->getJson("/api/v1/attendance/reports/{$report->id}");

    $response->assertOk()
        ->assertJsonPath('data.attributes.status', ReportStatus::Completed->value)
        ->assertJsonPath('data.attributes.data.summary.total_employees', 2);
});
