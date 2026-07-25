<?php

namespace App\Models;

use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rows here are written by LedgerService and nothing else, and are never
 * deleted. Correct a mistake with an `adjustment` entry.
 */
class EmployeeLedger extends Model
{
    use HasFactory;

    protected $table = 'employee_ledger';

    protected $fillable = [
        'employee_id',
        'entry_date',
        'type',
        'direction',
        'amount',
        'reference_type',
        'reference_id',
        'payment_method',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'type' => LedgerEntryType::class,
            'direction' => LedgerDirection::class,
            'payment_method' => PaymentMethod::class,
            'amount' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeCredits(Builder $query): Builder
    {
        return $query->where('direction', LedgerDirection::Credit);
    }

    public function scopeDebits(Builder $query): Builder
    {
        return $query->where('direction', LedgerDirection::Debit);
    }
}
