@extends('layouts.app')

@section('title', 'Products & Services')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h3 class="fw-bold mb-1">Products & Services Catalog</h3>
        <p class="text-muted mb-0 small">Inventory goods, billable consulting, and services linked to General Ledger accounts</p>
    </div>
    <div class="d-flex gap-2">
        @if(auth()->user()->isAccountant())
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newProductModal">
            <i class="fa-solid fa-plus me-1"></i> New Item / Service
        </button>
        @endif
        @if(auth()->user()->isAdmin())
        <a href="{{ route('imports.index') }}" class="btn btn-outline-warning btn-sm text-dark">
            <i class="fa-solid fa-file-arrow-up me-1"></i> Import Items (CSV)
        </a>
        @endif
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body p-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="btn-group btn-group-sm">
                <a href="{{ route('products.index') }}" class="btn {{ !request('type') ? 'btn-primary' : 'btn-outline-secondary' }}">All Items</a>
                <a href="{{ route('products.index', ['type' => 'goods']) }}" class="btn {{ request('type') == 'goods' ? 'btn-primary' : 'btn-outline-secondary' }}">Physical Goods</a>
                <a href="{{ route('products.index', ['type' => 'service']) }}" class="btn {{ request('type') == 'service' ? 'btn-primary' : 'btn-outline-secondary' }}">Services</a>
            </div>

            <form method="GET" action="{{ route('products.index') }}" class="d-flex gap-2" style="max-width: 320px;">
                @if(request('type'))
                    <input type="hidden" name="type" value="{{ request('type') }}">
                @endif
                <div class="input-group input-group-sm">
                    <input type="text" name="search" class="form-control" placeholder="Search code or name..." value="{{ request('search') }}">
                    <button class="btn btn-outline-secondary" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Products Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-finance mb-0">
            <thead>
                <tr>
                    <th style="width: 140px;">Code / SKU</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>GL Revenue Account</th>
                    <th class="num-col">Sale Price</th>
                    <th class="num-col">Cost Price</th>
                    <th class="num-col">In Stock</th>
                    <th class="text-end" style="width: 110px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $p)
                <tr>
                    <td class="font-monospace fw-bold text-primary">{{ $p->code }}</td>
                    <td>
                        <div class="fw-semibold">{{ $p->name }}</div>
                        @if($p->description)
                            <div class="small text-muted text-truncate" style="max-width: 250px;">{{ $p->description }}</div>
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ $p->type === 'goods' ? 'bg-info bg-opacity-10 text-info border border-info' : 'bg-primary bg-opacity-10 text-primary border border-primary' }} text-capitalize">
                            {{ $p->type }}
                        </span>
                    </td>
                    <td>
                        <span class="small font-monospace text-muted">{{ $p->incomeAccount->code }}</span> - {{ $p->incomeAccount->name }}
                    </td>
                    <td class="num-col fw-bold">${{ number_format($p->sale_price, 2) }}</td>
                    <td class="num-col">${{ number_format($p->purchase_price, 2) }}</td>
                    <td class="num-col {{ $p->quantity_on_hand <= 0 && $p->type === 'goods' ? 'text-danger fw-bold' : '' }}">
                        {{ $p->type === 'goods' ? number_format($p->quantity_on_hand, 0) : 'N/A' }}
                    </td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            @if(auth()->user()->isAccountant())
                            <a href="{{ route('products.edit', $p) }}" class="btn btn-outline-secondary" title="Edit">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </a>
                            @endif
                            @if(auth()->user()->isAdmin())
                            <form action="{{ route('products.destroy', $p) }}" method="POST" onsubmit="return confirm('Delete this product?');" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger" title="Delete">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">No products or services found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($products->hasPages())
    <div class="card-footer bg-white py-3">
        {{ $products->links() }}
    </div>
    @endif
</div>

<!-- New Product Modal -->
@if(auth()->user()->isAccountant())
<div class="modal fade" id="newProductModal" tabindex="-1" aria-labelledby="newProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('products.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="newProductModalLabel"><i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Add Product or Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold small">Code / SKU <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control" placeholder="e.g. IT-CONSULT-01" required>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold small">Item Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select" required>
                                <option value="goods">Physical Goods (Inventory)</option>
                                <option value="service">Service / Consulting</option>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold small">Default Tax Rate (%)</label>
                            <input type="number" step="0.01" min="0" max="100" name="tax_rate" class="form-control" value="0.00">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Item Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Senior Security Advisory Consulting" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small">Selling Price ($) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="sale_price" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small">Cost / Purchase Price ($)</label>
                            <input type="number" step="0.01" min="0" name="purchase_price" class="form-control" placeholder="0.00" value="0.00">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small">Revenue Account (Sales) <span class="text-danger">*</span></label>
                            <select name="income_account_id" class="form-select" required>
                                @foreach($accounts->where('type', 'revenue') as $acc)
                                    <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small">Expense Account (COGS/Purchases) <span class="text-danger">*</span></label>
                            <select name="expense_account_id" class="form-select" required>
                                @foreach($accounts->where('type', 'expense') as $acc)
                                    <option value="{{ $acc->id }}">{{ $acc->code }} - {{ $acc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small">Initial Quantity on Hand</label>
                            <input type="number" step="0.01" name="quantity_on_hand" class="form-control" value="0.00">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Description</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Description printed on invoices and bills..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-semibold">Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
