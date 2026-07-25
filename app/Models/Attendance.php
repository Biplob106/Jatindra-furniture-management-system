<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Attendance rows are never deleted. A wrong day is corrected by re-saving it,
 * which upserts the row and reconciles the matching ledger entry.
 */
class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendance';

    protected $fillable = [
        'employee_id',
        'shop_id',
        'work_date',
        'status',
        'in_time',
        'out_time',
        'overtime_hours',
        'overtime_rate',
        'note',
        'marked_by',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'status' => AttendanceStatus::class,
            'overtime_hours' => 'decimal:2',
            'overtime_rate' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
