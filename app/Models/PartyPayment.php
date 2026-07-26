<?php

namespace App\Models;

use App\Enums\CashPaymentMethod;
use App\Enums\PartyType;
use App\Enums\TransactionDirection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One handover of money to or from a party, which may settle several invoices
 * through its allocations.
 */
class PartyPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'party_type',
        'party_id',
        'direction',
        'payment_date',
        'amount',
        'account_id',
        'payment_method',
        'reference_no',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'party_type' => PartyType::class,
            'direction' => TransactionDirection::class,
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'payment_method' => CashPaymentMethod::class,
        ];
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'party_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'party_id');
    }

    public function scopeForSupplier(Builder $query, int $supplierId): Builder
    {
        return $query->where('party_type', PartyType::Supplier)->where('party_id', $supplierId);
    }
}
