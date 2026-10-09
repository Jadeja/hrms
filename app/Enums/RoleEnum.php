<?php

declare(strict_types=1);

namespace App\Enums;

enum RoleEnum: string
{
    case SystemAdmin = 'System Admin';
    case HrAdmin = 'HR Admin';
    case Employee = 'Employee';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
