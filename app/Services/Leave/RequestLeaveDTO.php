<?php

declare(strict_types=1);

namespace App\Services\Leave;

use App\Enums\LeaveType;
use Carbon\CarbonImmutable;

final readonly class RequestLeaveDTO
{
    public function __construct(
        public int $userId,
        public int $tenantId,
        public LeaveType $leaveType,
        public CarbonImmutable $startDate,
        public CarbonImmutable $endDate,
        public float $days,
        public string $reason,
    ) {}
}
