<?php

namespace App\Models;

use App\Enums\CashPaymentMethod;
use App\Enums\TransactionDirection;
use App\Enums\TransactionSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per actual movement of money. Written by CashService only, and
 * never deleted: a mistake is corrected with an `adjustment` row.
 */
class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'txn_date',
        'shop_id',
        'account_id',
        'direction',
        'amount',
        'source_type',
        'source_id',
        'party_type',
        'party_id',
        'payment_method',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'txn_date' => 'date',
            'direction' => TransactionDirection::class,
            'source_type' => TransactionSource::class,
            'payment_method' => CashPaymentMethod::class,
            'amount' => 'decimal:2',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
