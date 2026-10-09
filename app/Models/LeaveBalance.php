<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LeaveType;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\LeaveBalanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    /** @use HasFactory<LeaveBalanceFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'leave_type',
        'total_days',
        'used_days',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'leave_type' => LeaveType::class,
            'total_days' => 'float',
            'used_days' => 'float',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function availableDays(): float
    {
        return max(0.0, (float) $this->total_days - (float) $this->used_days);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
