<?php

namespace App\Models;

use App\Enums\PurchaseItemType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'purchase_id',
        'item_type',
        'item_id',
        'quantity',
        'unit',
        'unit_price',
        'line_total',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'item_type' => PurchaseItemType::class,
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * The material this line brought in, or null when the line is a readymade
     * product. No relation for products until phase 6 creates the table.
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'item_id');
    }
}
