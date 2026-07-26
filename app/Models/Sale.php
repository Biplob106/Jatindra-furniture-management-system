<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Sale extends Model
{
    use HasFactory;

    /**
     * invoice_no is absent: NumberSeries issues it. paid_amount, due_amount
     * and the totals follow from the lines and the payment, never from a form.
     */
    protected $fillable = [
        'customer_id',
        'customer_name',
        'customer_phone',
        'shop_id',
        'sale_date',
        'discount',
        'delivery_charge',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'delivery_charge' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_amount' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function allocations(): MorphMany
    {
        return $this->morphMany(PaymentAllocation::class, 'allocatable');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOwing(Builder $query): Builder
    {
        return $query->where('due_amount', '>', 0);
    }

    /**
     * The name to print, whoever bought it. A walk-in has no customer row, so
     * the invoice falls back to what was written on it at the counter.
     */
    public function buyerName(): string
    {
        return $this->customer?->name ?? $this->customer_name ?? 'নগদ ক্রেতা';
    }
}
