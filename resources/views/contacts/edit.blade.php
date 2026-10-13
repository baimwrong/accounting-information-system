@extends('layouts.app')

@section('title', 'Edit Contact - ' . $contact->name)

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-pen-to-square text-primary fs-5"></i>
                    <h5 class="card-title mb-0 fw-bold">Edit Contact: {{ $contact->name }}</h5>
                </div>
                <a href="{{ route('contacts.show', $contact) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Details
                </a>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('contacts.update', $contact) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Contact Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select" required>
                                <option value="customer" {{ old('type', $contact->type) === 'customer' ? 'selected' : '' }}>Customer</option>
                                <option value="vendor" {{ old('type', $contact->type) === 'vendor' ? 'selected' : '' }}>Vendor / Supplier</option>
                                <option value="both" {{ old('type', $contact->type) === 'both' ? 'selected' : '' }}>Both Customer & Vendor</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Primary Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $contact->name) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Company Name</label>
                            <input type="text" name="company_name" class="form-control" value="{{ old('company_name', $contact->company_name) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Tax ID / VAT #</label>
                            <input type="text" name="tax_number" class="form-control" value="{{ old('tax_number', $contact->tax_number) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Email Address</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $contact->email) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Phone Number</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone', $contact->phone) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Address</label>
                            <textarea name="address" class="form-control" rows="2">{{ old('address', $contact->address) }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Credit Limit ($)</label>
                            <input type="number" step="0.01" min="0" name="credit_limit" class="form-control" value="{{ old('credit_limit', $contact->credit_limit) }}">
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" {{ old('is_active', $contact->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold small" for="isActive">Contact is Active</label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                        <a href="{{ route('contacts.show', $contact) }}" class="btn btn-secondary btn-sm">Cancel</a>
                        <button type="submit" class="btn btn-primary btn-sm fw-semibold">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Update Contact
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
