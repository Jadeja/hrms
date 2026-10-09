<?php

declare(strict_types=1);

namespace App\Services\Leave;

use App\Enums\LeaveStatus;
use App\Exceptions\InvalidLeaveStateException;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class RejectLeaveService
{
    /**
     * @throws InvalidLeaveStateException
     */
    public function execute(int $leaveRequestId, User $approver, ?string $reason = null): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequestId, $approver, $reason): LeaveRequest {
            /** @var LeaveRequest|null $leaveRequest */
            $leaveRequest = LeaveRequest::where('id', $leaveRequestId)
                ->lockForUpdate()
                ->first();

            if ($leaveRequest === null) {
                throw new InvalidLeaveStateException('Leave request not found.');
            }

            if ($leaveRequest->status !== LeaveStatus::Pending) {
                throw new InvalidLeaveStateException('Only pending leave requests can be rejected.');
            }

            $leaveRequest->update([
                'status' => LeaveStatus::Rejected,
                'approved_by' => $approver->id,
                'rejection_reason' => $reason,
            ]);

            return $leaveRequest->fresh();
        });
    }
}
