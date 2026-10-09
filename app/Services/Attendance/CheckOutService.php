<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Exceptions\AttendanceException;
use App\Models\Attendance;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final readonly class CheckOutService
{
    /**
     * @throws AttendanceException
     */
    public function execute(User $user, CarbonInterface $checkOutTime): Attendance
    {
        $date = $checkOutTime->toDateString();

        return DB::transaction(function () use ($user, $checkOutTime, $date): Attendance {
            /** @var Attendance|null $record */
            $record = Attendance::where('user_id', $user->id)
                ->whereDate('date', $date)
                ->lockForUpdate()
                ->first();

            if ($record === null || $record->check_in_at === null) {
                throw new AttendanceException('You cannot check out without checking in first.');
            }

            if ($record->check_out_at !== null) {
                throw new AttendanceException('You have already checked out for today.');
            }

            $record->update([
                'check_out_at' => $checkOutTime,
            ]);

            return $record->fresh();
        });
    }
}
