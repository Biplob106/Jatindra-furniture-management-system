<?php

namespace App\Models;

use App\Enums\MaterialMovementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialMovement extends Model
{
    use HasFactory;

    /** created_at only. A movement is an event: it does not get edited. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'material_id',
        'movement_date',
        'type',
        'quantity',
        'unit_cost',
        'reference_type',
        'reference_id',
        'order_id',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'movement_date' => 'date',
            'type' => MaterialMovementType::class,
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /** The job that consumed it, when it was not general stock. */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
