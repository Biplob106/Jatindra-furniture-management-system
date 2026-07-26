<?php

namespace App\Models;

use App\Enums\LedgerDirection;
use App\Enums\SupplierLedgerEntryType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rows here are written by LedgerService and nothing else, and are never
 * deleted. Correct a mistake with an `adjustment` entry.
 */
class SupplierLedger extends Model
{
    use HasFactory;

    protected $table = 'supplier_ledger';

    protected $fillable = [
        'supplier_id',
        'entry_date',
        'type',
        'direction',
        'amount',
        'reference_type',
        'reference_id',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'type' => SupplierLedgerEntryType::class,
            'direction' => LedgerDirection::class,
            'amount' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** What we owe them more of. */
    public function scopeCredits(Builder $query): Builder
    {
        return $query->where('direction', LedgerDirection::Credit);
    }

    /** What we have paid down. */
    public function scopeDebits(Builder $query): Builder
    {
        return $query->where('direction', LedgerDirection::Debit);
    }
}
