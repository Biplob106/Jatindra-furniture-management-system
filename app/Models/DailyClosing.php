<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyClosing extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'shop_id',
        'closing_date',
        'opening_balance',
        'total_in',
        'total_out',
        'net_amount',
        'expected_closing',
        'counted_cash',
        'difference',
        'credit_purchase_today',
        'total_payable',
        'total_receivable',
        'closed_by',
        'closed_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'closing_date' => 'date',
            'closed_at' => 'datetime',
            'opening_balance' => 'decimal:2',
            'total_in' => 'decimal:2',
            'total_out' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'expected_closing' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'difference' => 'decimal:2',
            'credit_purchase_today' => 'decimal:2',
            'total_payable' => 'decimal:2',
            'total_receivable' => 'decimal:2',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
