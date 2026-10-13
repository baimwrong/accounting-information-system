@extends('layouts.app')

@section('title', 'New Contact')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-user-plus text-primary fs-5"></i>
                    <h5 class="card-title mb-0 fw-bold">Add New Contact</h5>
                </div>
                <a href="{{ route('contacts.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Directory
                </a>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('contacts.store') }}" method="POST">
                    @csrf

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Contact Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select" required>
                                <option value="customer" {{ old('type') === 'customer' ? 'selected' : '' }}>Customer (Accounts Receivable)</option>
                                <option value="vendor" {{ old('type') === 'vendor' ? 'selected' : '' }}>Vendor / Supplier (Accounts Payable)</option>
                                <option value="both" {{ old('type') === 'both' ? 'selected' : '' }}>Both Customer & Vendor</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Contact / Primary Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Acme Corporation or John Doe" value="{{ old('name') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Company / Legal Name</label>
                            <input type="text" name="company_name" class="form-control" placeholder="e.g. Acme Global Holdings LLC" value="{{ old('company_name') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Tax Identification #</label>
                            <input type="text" name="tax_number" class="form-control" placeholder="e.g. EIN / VAT / Tax ID" value="{{ old('tax_number') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="billing@company.com" value="{{ old('email') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Phone Number</label>
                            <input type="text" name="phone" class="form-control" placeholder="+1 (555) 012-3456" value="{{ old('phone') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Billing / Shipping Address</label>
                            <textarea name="address" class="form-control" rows="2" placeholder="Street, City, State/Province, Postal Code...">{{ old('address') }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Credit Limit ($)</label>
                            <input type="number" step="0.01" min="0" name="credit_limit" class="form-control" placeholder="0.00" value="{{ old('credit_limit') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Opening Balance ($)</label>
                            <input type="number" step="0.01" name="opening_balance" class="form-control" placeholder="0.00" value="{{ old('opening_balance', '0.00') }}">
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                        <a href="{{ route('contacts.index') }}" class="btn btn-secondary btn-sm">Cancel</a>
                        <button type="submit" class="btn btn-primary btn-sm fw-semibold">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Save Contact
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
