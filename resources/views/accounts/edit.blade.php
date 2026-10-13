@extends('layouts.app')

@section('title', 'Edit Account - ' . $account->code)

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-pen-to-square text-primary fs-5"></i>
                    <h5 class="card-title mb-0 fw-bold">Edit Account: {{ $account->code }} - {{ $account->name }}</h5>
                </div>
                <a href="{{ route('accounts.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Accounts
                </a>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('accounts.update', $account) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small">Account Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control" value="{{ old('code', $account->code) }}" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small">Classification <span class="text-danger">*</span></label>
                            <select name="type" class="form-select" required>
                                <option value="asset" {{ old('type', $account->type) == 'asset' ? 'selected' : '' }}>Asset</option>
                                <option value="liability" {{ old('type', $account->type) == 'liability' ? 'selected' : '' }}>Liability</option>
                                <option value="equity" {{ old('type', $account->type) == 'equity' ? 'selected' : '' }}>Equity</option>
                                <option value="revenue" {{ old('type', $account->type) == 'revenue' ? 'selected' : '' }}>Revenue</option>
                                <option value="expense" {{ old('type', $account->type) == 'expense' ? 'selected' : '' }}>Expense</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Account Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $account->name) }}" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small">Sub-Type</label>
                            <input type="text" name="sub_type" class="form-control" value="{{ old('sub_type', $account->sub_type) }}">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small">Normal Balance <span class="text-danger">*</span></label>
                            <select name="normal_balance" class="form-select" required>
                                <option value="debit" {{ old('normal_balance', $account->normal_balance) == 'debit' ? 'selected' : '' }}>Debit</option>
                                <option value="credit" {{ old('normal_balance', $account->normal_balance) == 'credit' ? 'selected' : '' }}>Credit</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Parent Account</label>
                            <select name="parent_id" class="form-select">
                                <option value="">-- None (Top Level) --</option>
                                @foreach($parentAccounts as $parent)
                                    <option value="{{ $parent->id }}" {{ old('parent_id', $account->parent_id) == $parent->id ? 'selected' : '' }}>
                                        {{ $parent->code }} - {{ $parent->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Description</label>
                            <textarea name="description" class="form-control" rows="3">{{ old('description', $account->description) }}</textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" {{ old('is_active', $account->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold small" for="isActive">Account is Active</label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                        <a href="{{ route('accounts.index') }}" class="btn btn-secondary btn-sm">Cancel</a>
                        <button type="submit" class="btn btn-primary btn-sm fw-semibold">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Update Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
