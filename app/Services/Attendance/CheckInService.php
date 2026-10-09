<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Exceptions\AttendanceException;
use App\Models\Attendance;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final readonly class CheckInService
{
    /**
     * @throws AttendanceException
     */
    public function execute(User $user, CarbonInterface $checkInTime, ?string $notes = null): Attendance
    {
        $date = $checkInTime->toDateString();

        return DB::transaction(function () use ($user, $checkInTime, $date, $notes): Attendance {
            /** @var Attendance|null $existing */
            $existing = Attendance::where('user_id', $user->id)
                ->whereDate('date', $date)
                ->lockForUpdate()
                ->first();

            if ($existing !== null && $existing->check_in_at !== null) {
                throw new AttendanceException('You have already checked in for today.');
            }

            // Standard check-in time is 09:15 AM
            $lateThreshold = $checkInTime->copy()->setTime(9, 15, 0);
            $status = $checkInTime->greaterThan($lateThreshold)
                ? AttendanceStatus::Late
                : AttendanceStatus::Present;

            if ($existing !== null) {
                $existing->update([
                    'check_in_at' => $checkInTime,
                    'status' => $status,
                    'notes' => $notes ?? $existing->notes,
                ]);

                return $existing->fresh();
            }

            return Attendance::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'date' => $date,
                'check_in_at' => $checkInTime,
                'status' => $status,
                'notes' => $notes,
            ]);
        });
    }
}
