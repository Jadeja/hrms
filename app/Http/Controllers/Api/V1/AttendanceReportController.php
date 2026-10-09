<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Attendance\GenerateReportRequest;
use App\Http\Resources\Api\V1\MonthlyAttendanceReportResource;
use App\Models\MonthlyAttendanceReport;
use App\Models\Tenant;
use App\Services\Report\GenerateAttendanceReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;
use Symfony\Component\HttpFoundation\Response;

#[Prefix('api/v1/attendance/reports')]
#[Middleware(['auth:sanctum', 'tenant', 'role:System Admin|HR Admin'])]
class AttendanceReportController extends Controller
{
    #[Post('generate')]
    public function generate(GenerateReportRequest $request, GenerateAttendanceReportService $service): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant') ?? Tenant::current();

        $report = $service->execute(
            (int) $tenant->id,
            (int) $request->integer('month'),
            (int) $request->integer('year'),
            $request->user()
        );

        return (new MonthlyAttendanceReportResource($report))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    #[Get('')]
    public function index(): AnonymousResourceCollection
    {
        $reports = MonthlyAttendanceReport::latest()->paginate();

        return MonthlyAttendanceReportResource::collection($reports);
    }

    #[Get('{report}')]
    public function show(MonthlyAttendanceReport $report): MonthlyAttendanceReportResource
    {
        return new MonthlyAttendanceReportResource($report);
    }
}
