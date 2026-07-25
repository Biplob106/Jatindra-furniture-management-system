<?php

namespace App\Models;

use App\Enums\OrderItemWorkStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Work handed to one worker on one order item. Reaching `done` with an
 * agreed_amount is what pays a piece worker.
 */
class OrderItemWork extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'order_item_id',
        'employee_id',
        'trade_id',
        'work_type',
        'agreed_amount',
        'assigned_date',
        'started_at',
        'completed_at',
        'status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'agreed_amount' => 'decimal:2',
            'assigned_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'status' => OrderItemWorkStatus::class,
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }
}
