<?php

namespace App\Models;

use App\Enums\CncJobStatus;
use App\Enums\CncMaterialBy;
use App\Enums\CncRateType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CncJob extends Model
{
    use HasFactory;

    /**
     * job_no is absent: NumberSeries issues it. The money columns follow from
     * quantity and rate, and from payments, never from a form.
     */
    protected $fillable = [
        'customer_id',
        'customer_name',
        'customer_phone',
        'order_id',
        'job_date',
        'description',
        'material_by',
        'rate_type',
        'quantity',
        'rate',
        'machine_hours',
        'operator_id',
        'delivery_date',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'job_date' => 'date',
            'delivery_date' => 'date',
            'material_by' => CncMaterialBy::class,
            'rate_type' => CncRateType::class,
            'status' => CncJobStatus::class,
            'quantity' => 'decimal:2',
            'rate' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_amount' => 'decimal:2',
            'machine_hours' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Set when the job is cutting parts for one of our own orders. */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'operator_id');
    }

    public function allocations(): MorphMany
    {
        return $this->morphMany(PaymentAllocation::class, 'allocatable');
    }

    /** Still in the shop's hands. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [CncJobStatus::Delivered, CncJobStatus::Cancelled]);
    }

    public function scopeOwing(Builder $query): Builder
    {
        return $query->where('due_amount', '>', 0);
    }

    /**
     * The name to print. A job taken over the counter has no customer row.
     */
    public function buyerName(): string
    {
        return $this->customer?->name ?? $this->customer_name ?? 'নগদ ক্রেতা';
    }
}
