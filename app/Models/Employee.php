<?php

namespace App\Models;

use App\Enums\WageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_code',
        'name',
        'phone',
        'address',
        'photo',
        'nid_no',
        'trade_id',
        'shop_id',
        'wage_type',
        'daily_rate',
        'monthly_salary',
        'joining_date',
        'guarantor_name',
        'guarantor_phone',
        'opening_advance',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'wage_type' => WageType::class,
            'daily_rate' => 'decimal:2',
            'monthly_salary' => 'decimal:2',
            'opening_advance' => 'decimal:2',
            'joining_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
