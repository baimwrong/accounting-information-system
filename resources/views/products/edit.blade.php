@extends('layouts.app')

@section('title', 'Edit Item - ' . $product->name)

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-pen-to-square text-primary fs-5"></i>
                    <h5 class="card-title mb-0 fw-bold">Edit Item: {{ $product->code }} - {{ $product->name }}</h5>
                </div>
                <a href="{{ route('products.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Catalog
                </a>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('products.update', $product) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold small">Code / SKU <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control" value="{{ old('code', $product->code) }}" required>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold small">Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select" required>
                                <option value="goods" {{ old('type', $product->type) === 'goods' ? 'selected' : '' }}>Physical Goods</option>
                                <option value="service" {{ old('type', $product->type) === 'service' ? 'selected' : '' }}>Service</option>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold small">Tax Rate (%)</label>
                            <input type="number" step="0.01" min="0" max="100" name="tax_rate" class="form-control" value="{{ old('tax_rate', $product->tax_rate) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Item Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $product->name) }}" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small">Sale Price ($) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="sale_price" class="form-control" value="{{ old('sale_price', $product->sale_price) }}" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small">Purchase Cost ($)</label>
                            <input type="number" step="0.01" min="0" name="purchase_price" class="form-control" value="{{ old('purchase_price', $product->purchase_price) }}">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small">Income Account (Sales) <span class="text-danger">*</span></label>
                            <select name="income_account_id" class="form-select" required>
                                @foreach($accounts->where('type', 'revenue') as $acc)
                                    <option value="{{ $acc->id }}" {{ old('income_account_id', $product->income_account_id) == $acc->id ? 'selected' : '' }}>
                                        {{ $acc->code }} - {{ $acc->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small">Expense Account (COGS) <span class="text-danger">*</span></label>
                            <select name="expense_account_id" class="form-select" required>
                                @foreach($accounts->where('type', 'expense') as $acc)
                                    <option value="{{ $acc->id }}" {{ old('expense_account_id', $product->expense_account_id) == $acc->id ? 'selected' : '' }}>
                                        {{ $acc->code }} - {{ $acc->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small">Quantity on Hand</label>
                            <input type="number" step="0.01" name="quantity_on_hand" class="form-control" value="{{ old('quantity_on_hand', $product->quantity_on_hand) }}">
                        </div>
                        <div class="col-sm-6">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" {{ old('is_active', $product->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold small" for="isActive">Item is Active</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Description</label>
                            <textarea name="description" class="form-control" rows="2">{{ old('description', $product->description) }}</textarea>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                        <a href="{{ route('products.index') }}" class="btn btn-secondary btn-sm">Cancel</a>
                        <button type="submit" class="btn btn-primary btn-sm fw-semibold">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Update Item
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
