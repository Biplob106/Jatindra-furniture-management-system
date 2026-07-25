<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Order extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    /**
     * order_no is absent on purpose. It is issued by NumberSeries when the
     * order is confirmed and must never arrive from a form.
     *
     * paid_amount and due_amount are absent too: they are recalculated from
     * payments, not set by hand.
     */
    protected $fillable = [
        'customer_id',
        'shop_id',
        'order_date',
        'expected_delivery_date',
        'status',
        'subtotal',
        'discount',
        'delivery_charge',
        'total_amount',
        'delivery_address',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_delivery_date' => 'date',
            'delivered_at' => 'datetime',
            'status' => OrderStatus::class,
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
        return $this->hasMany(OrderItem::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Orders still needing work or delivery. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [OrderStatus::Delivered, OrderStatus::Cancelled]);
    }

    public function scopeOwing(Builder $query): Builder
    {
        return $query->where('due_amount', '>', 0);
    }

    /**
     * Photos of the piece, design drawings, the signed slip. Conversions
     * handle the 1600px / ~200KB compression the rollout checklist asks for.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');
        $this->addMediaCollection('designs');
    }
}
