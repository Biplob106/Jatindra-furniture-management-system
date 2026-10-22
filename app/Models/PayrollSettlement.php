<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A frozen snapshot of one worker's payroll for a finalized period. Never the
 * source of a live balance — that is always computed from the ledger. An
 * active (not reversed) row means the period is finalized for that worker.
 */
class PayrollSettlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'shop_id',
        'period_start',
        'period_end',
        'earned',
        'night',
        'paid',
        'deductions',
        'net_balance',
        'settled_at',
        'settled_by',
        'reversed_at',
        'reversed_by',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'earned' => 'decimal:2',
            'night' => 'decimal:2',
            'paid' => 'decimal:2',
            'deductions' => 'decimal:2',
            'net_balance' => 'decimal:2',
            'settled_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
