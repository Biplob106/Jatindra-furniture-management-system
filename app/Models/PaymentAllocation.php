<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * How much of one payment went against one invoice. What lets a single
 * handover clear three challans and leave the fourth half open.
 */
class PaymentAllocation extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'party_payment_id',
        'allocatable_type',
        'allocatable_id',
        'allocated_amount',
    ];

    protected function casts(): array
    {
        return [
            'allocated_amount' => 'decimal:2',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(PartyPayment::class, 'party_payment_id');
    }

    public function allocatable(): MorphTo
    {
        return $this->morphTo();
    }
}
