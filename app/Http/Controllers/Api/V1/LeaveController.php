<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\LeaveType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Leave\RejectLeaveRequest;
use App\Http\Requests\Api\V1\Leave\StoreLeaveRequest;
use App\Http\Resources\Api\V1\LeaveBalanceResource;
use App\Http\Resources\Api\V1\LeaveRequestResource;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\Tenant;
use App\Services\Leave\ApproveLeaveService;
use App\Services\Leave\RejectLeaveService;
use App\Services\Leave\RequestLeaveDTO;
use App\Services\Leave\RequestLeaveService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;
use Symfony\Component\HttpFoundation\Response;

#[Prefix('api/v1/leaves')]
#[Middleware(['auth:sanctum', 'tenant'])]
class LeaveController extends Controller
{
    #[Get('balances')]
    public function balances(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $targetUserId = $user->id;

        // HR Admin / System Admin can view balances for any employee in the tenant
        if (($user->isHrAdmin() || $user->isSystemAdmin()) && $request->has('user_id')) {
            $targetUserId = (int) $request->input('user_id');
        }

        $balances = LeaveBalance::where('user_id', $targetUserId)->get();

        return LeaveBalanceResource::collection($balances);
    }

    #[Post('requests')]
    public function storeRequest(StoreLeaveRequest $request, RequestLeaveService $service): JsonResponse
    {
        $user = $request->user();
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant') ?? Tenant::current();

        $startDate = CarbonImmutable::parse($request->string('start_date')->value());
        $endDate = CarbonImmutable::parse($request->string('end_date')->value());
        // Calculate inclusive day count
        $days = (float) ($startDate->diffInDays($endDate) + 1);

        $dto = new RequestLeaveDTO(
            userId: (int) $user->id,
            tenantId: (int) $tenant->id,
            leaveType: LeaveType::from($request->string('leave_type')->value()),
            startDate: $startDate,
            endDate: $endDate,
            days: $days,
            reason: $request->string('reason')->value(),
        );

        $leaveRequest = $service->execute($dto);

        return (new LeaveRequestResource($leaveRequest))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[Get('my-requests')]
    public function myRequests(Request $request): AnonymousResourceCollection
    {
        $requests = LeaveRequest::where('user_id', $request->user()->id)
            ->latest()
            ->paginate();

        return LeaveRequestResource::collection($requests);
    }

    #[Get('requests', middleware: 'role:System Admin|HR Admin')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $requests = LeaveRequest::with(['user', 'approver'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('user_id'), fn ($q, $userId) => $q->where('user_id', $userId))
            ->latest()
            ->paginate();

        return LeaveRequestResource::collection($requests);
    }

    #[Post('requests/{leaveRequest}/approve', middleware: 'role:System Admin|HR Admin')]
    public function approve(LeaveRequest $leaveRequest, ApproveLeaveService $service, Request $request): LeaveRequestResource
    {
        $approved = $service->execute((int) $leaveRequest->id, $request->user());

        return new LeaveRequestResource($approved);
    }

    #[Post('requests/{leaveRequest}/reject', middleware: 'role:System Admin|HR Admin')]
    public function reject(LeaveRequest $leaveRequest, RejectLeaveRequest $request, RejectLeaveService $service): LeaveRequestResource
    {
        $rejected = $service->execute(
            (int) $leaveRequest->id,
            $request->user(),
            $request->input('reason')
        );

        return new LeaveRequestResource($rejected);
    }
}
