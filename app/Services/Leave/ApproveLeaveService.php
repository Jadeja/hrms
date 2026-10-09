<?php

declare(strict_types=1);

namespace App\Services\Leave;

use App\Enums\LeaveStatus;
use App\Exceptions\InsufficientLeaveBalanceException;
use App\Exceptions\InvalidLeaveStateException;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class ApproveLeaveService
{
    /**
     * @throws InvalidLeaveStateException
     * @throws InsufficientLeaveBalanceException
     */
    public function execute(int $leaveRequestId, User $approver): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequestId, $approver): LeaveRequest {
            /** @var LeaveRequest|null $leaveRequest */
            $leaveRequest = LeaveRequest::where('id', $leaveRequestId)
                ->lockForUpdate()
                ->first();

            if ($leaveRequest === null) {
                throw new InvalidLeaveStateException('Leave request not found.');
            }

            if ($leaveRequest->status !== LeaveStatus::Pending) {
                throw new InvalidLeaveStateException('Only pending leave requests can be approved.');
            }

            /** @var LeaveBalance|null $balance */
            $balance = LeaveBalance::where('user_id', $leaveRequest->user_id)
                ->where('leave_type', $leaveRequest->leave_type->value)
                ->lockForUpdate()
                ->first();

            if ($balance === null) {
                throw new InsufficientLeaveBalanceException('No leave balance allocated for this leave type.');
            }

            if ($balance->availableDays() < $leaveRequest->days) {
                throw new InsufficientLeaveBalanceException(
                    sprintf('Insufficient balance to approve leave. Available: %.1f, Required: %.1f.', $balance->availableDays(), $leaveRequest->days)
                );
            }

            $balance->used_days += $leaveRequest->days;
            $balance->save();

            $leaveRequest->update([
                'status' => LeaveStatus::Approved,
                'approved_by' => $approver->id,
            ]);

            return $leaveRequest->fresh();
        });
    }
}
