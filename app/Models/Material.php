<?php

namespace App\Models;

use App\Enums\MaterialCategory;
use App\Enums\MaterialUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * current_stock and avg_cost are absent: they are maintained from
     * material_movements, not set from a form.
     */
    protected $fillable = [
        'name',
        'category',
        'unit',
        'min_stock',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'category' => MaterialCategory::class,
            'unit' => MaterialUnit::class,
            'current_stock' => 'decimal:3',
            'avg_cost' => 'decimal:2',
            'min_stock' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(MaterialMovement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Stock at or below the reorder line. Feeds the low stock alert. */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('current_stock', '<=', 'min_stock')
            ->where('min_stock', '>', 0);
    }
}
