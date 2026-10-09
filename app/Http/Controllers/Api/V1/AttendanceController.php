<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Attendance\CheckInRequest;
use App\Http\Resources\Api\V1\AttendanceResource;
use App\Models\Attendance;
use App\Services\Attendance\CheckInService;
use App\Services\Attendance\CheckOutService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;
use Symfony\Component\HttpFoundation\Response;

#[Prefix('api/v1/attendance')]
#[Middleware(['auth:sanctum', 'tenant'])]
class AttendanceController extends Controller
{
    #[Post('check-in')]
    public function checkIn(CheckInRequest $request, CheckInService $service): JsonResponse
    {
        $attendance = $service->execute(
            $request->user(),
            Carbon::now(),
            $request->input('notes')
        );

        return (new AttendanceResource($attendance))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[Post('check-out')]
    public function checkOut(Request $request, CheckOutService $service): AttendanceResource
    {
        $attendance = $service->execute(
            $request->user(),
            Carbon::now()
        );

        return new AttendanceResource($attendance);
    }

    #[Get('my-records')]
    public function myRecords(Request $request): AnonymousResourceCollection
    {
        $records = Attendance::where('user_id', $request->user()->id)
            ->latest('date')
            ->paginate();

        return AttendanceResource::collection($records);
    }

    #[Get('', middleware: 'role:System Admin|HR Admin')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $records = Attendance::with('user')
            ->when($request->query('user_id'), fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($request->query('date'), fn ($q, $date) => $q->where('date', $date))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest('date')
            ->paginate();

        return AttendanceResource::collection($records);
    }
}
