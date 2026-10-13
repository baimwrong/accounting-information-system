<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'company_name',
        'email',
        'phone',
        'tax_number',
        'address',
        'credit_limit',
        'opening_balance',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:4',
            'opening_balance' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'customer_id');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class, 'vendor_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'contact_id');
    }

    public function scopeCustomers(Builder $query): Builder
    {
        return $query->whereIn('type', ['customer', 'both']);
    }

    public function scopeVendors(Builder $query): Builder
    {
        return $query->whereIn('type', ['vendor', 'both']);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getOutstandingBalanceAttribute(): float
    {
        if ($this->type === 'customer' || $this->type === 'both') {
            return (float) $this->invoices()
                ->whereIn('status', ['sent', 'partially_paid', 'overdue'])
                ->get()
                ->sum(fn ($inv) => (float) $inv->total_amount - (float) $inv->paid_amount);
        }

        return (float) $this->bills()
            ->whereIn('status', ['approved', 'partially_paid', 'overdue'])
            ->get()
            ->sum(fn ($b) => (float) $b->total_amount - (float) $b->paid_amount);
    }
}
