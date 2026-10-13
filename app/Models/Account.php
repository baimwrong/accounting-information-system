<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'sub_type',
        'normal_balance',
        'parent_id',
        'description',
        'is_active',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function journalEntryItems(): HasMany
    {
        return $this->hasMany(JournalEntryItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeAssets(Builder $query): Builder
    {
        return $query->where('type', 'asset');
    }

    public function scopeLiabilities(Builder $query): Builder
    {
        return $query->where('type', 'liability');
    }

    public function scopeEquity(Builder $query): Builder
    {
        return $query->where('type', 'equity');
    }

    public function scopeRevenue(Builder $query): Builder
    {
        return $query->where('type', 'revenue');
    }

    public function scopeExpenses(Builder $query): Builder
    {
        return $query->where('type', 'expense');
    }

    /**
     * Compute current net balance for posted journal entries.
     */
    public function getBalanceAttribute(): float
    {
        $debit = (float) $this->journalEntryItems()
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted'))
            ->where('type', 'debit')
            ->sum('amount');

        $credit = (float) $this->journalEntryItems()
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted'))
            ->where('type', 'credit')
            ->sum('amount');

        if ($this->normal_balance === 'debit') {
            return $debit - $credit;
        }

        return $credit - $debit;
    }

    public function getFormattedBalanceAttribute(): string
    {
        return number_format($this->balance, 2);
    }
}
