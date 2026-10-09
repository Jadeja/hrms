<?php

declare(strict_types=1);

namespace App\Services\Leave;

use App\Enums\LeaveStatus;
use App\Exceptions\InsufficientLeaveBalanceException;
use App\Exceptions\OverlappingLeaveException;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use Illuminate\Support\Facades\DB;

final readonly class RequestLeaveService
{
    /**
     * @throws InsufficientLeaveBalanceException
     * @throws OverlappingLeaveException
     */
    public function execute(RequestLeaveDTO $dto): LeaveRequest
    {
        return DB::transaction(function () use ($dto): LeaveRequest {
            /** @var LeaveBalance|null $balance */
            $balance = LeaveBalance::where('user_id', $dto->userId)
                ->where('leave_type', $dto->leaveType->value)
                ->lockForUpdate()
                ->first();

            if ($balance === null) {
                throw new InsufficientLeaveBalanceException('No leave balance allocated for '.$dto->leaveType->value.' leave.');
            }

            if ($balance->availableDays() < $dto->days) {
                throw new InsufficientLeaveBalanceException(
                    sprintf('Insufficient leave balance. Available: %.1f days, Requested: %.1f days.', $balance->availableDays(), $dto->days)
                );
            }

            // Pessimistically check for overlapping pending or approved leave requests
            $hasOverlap = LeaveRequest::where('user_id', $dto->userId)
                ->whereIn('status', [LeaveStatus::Pending->value, LeaveStatus::Approved->value])
                ->where(function ($query) use ($dto): void {
                    $startStr = $dto->startDate->toDateString();
                    $endStr = $dto->endDate->toDateString();

                    $query->whereBetween('start_date', [$startStr, $endStr])
                        ->orWhereBetween('end_date', [$startStr, $endStr])
                        ->orWhere(function ($q) use ($startStr, $endStr): void {
                            $q->where('start_date', '<=', $startStr)
                                ->where('end_date', '>=', $endStr);
                        });
                })
                ->lockForUpdate()
                ->exists();

            if ($hasOverlap) {
                throw new OverlappingLeaveException('You already have an existing leave request spanning these dates.');
            }

            return LeaveRequest::create([
                'tenant_id' => $dto->tenantId,
                'user_id' => $dto->userId,
                'leave_type' => $dto->leaveType,
                'start_date' => $dto->startDate->toDateString(),
                'end_date' => $dto->endDate->toDateString(),
                'days' => $dto->days,
                'reason' => $dto->reason,
                'status' => LeaveStatus::Pending,
            ]);
        });
    }
}
