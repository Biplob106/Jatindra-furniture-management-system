<?php

namespace App\Models;

use App\Enums\PurchasePaymentType;
use App\Enums\PurchaseStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Purchase extends Model
{
    use HasFactory;

    /**
     * purchase_no is absent on purpose: NumberSeries issues it and it must
     * never arrive from a form. paid_amount, due_amount and status are absent
     * too, since they are recalculated from payment allocations.
     */
    protected $fillable = [
        'supplier_id',
        'shop_id',
        'purchase_date',
        'reference_no',
        'payment_type',
        'payment_due_date',
        'subtotal',
        'transport_cost',
        'discount',
        'total_amount',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'payment_due_date' => 'date',
            'payment_type' => PurchasePaymentType::class,
            'status' => PurchaseStatus::class,
            'subtotal' => 'decimal:2',
            'transport_cost' => 'decimal:2',
            'discount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_amount' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function allocations(): MorphMany
    {
        return $this->morphMany(PaymentAllocation::class, 'allocatable');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Still owed something. What the payable and aging lists run on. */
    public function scopeOwing(Builder $query): Builder
    {
        return $query->where('due_amount', '>', 0)
            ->whereIn('status', [PurchaseStatus::Pending, PurchaseStatus::Partial]);
    }

    /** Past its credit terms as of the given date. */
    public function scopeOverdue(Builder $query, string $asOf): Builder
    {
        return $query->owing()
            ->whereNotNull('payment_due_date')
            ->where('payment_due_date', '<', $asOf);
    }
}
