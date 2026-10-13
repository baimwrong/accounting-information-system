<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'income_account_id',
        'expense_account_id',
        'asset_account_id',
        'sale_price',
        'purchase_price',
        'tax_rate',
        'quantity_on_hand',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:4',
            'purchase_price' => 'decimal:4',
            'tax_rate' => 'decimal:2',
            'quantity_on_hand' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function incomeAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'income_account_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function assetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'asset_account_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeGoods(Builder $query): Builder
    {
        return $query->where('type', 'goods');
    }

    public function scopeServices(Builder $query): Builder
    {
        return $query->where('type', 'service');
    }
}
