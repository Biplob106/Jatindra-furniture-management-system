<?php

namespace App\Models;

use App\Enums\DimensionUnit;
use App\Enums\OrderItemStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'category_id',
        'item_name',
        'description',
        'wood_type',
        'design_no',
        'length',
        'width',
        'height',
        'dimension_unit',
        'polish_type',
        'quantity',
        'unit_price',
        'line_total',
        'target_date',
        'status',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'length' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'dimension_unit' => DimensionUnit::class,
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'target_date' => 'date',
            'status' => OrderItemStatus::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function works(): HasMany
    {
        return $this->hasMany(OrderItemWork::class);
    }
}
