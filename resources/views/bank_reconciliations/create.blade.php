@extends('layouts.app')

@section('title', 'Start Bank Reconciliation')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Start Bank Reconciliation</h3>
        <p class="text-muted mb-0 small">Initialize a new monthly or periodic reconciliation session from your bank statement</p>
    </div>
    <a href="{{ route('bank-reconciliations.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-arrow-left me-1"></i> Back to Reconciliations
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card p-4 shadow-sm border">
            <form action="{{ route('bank-reconciliations.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Bank / Cash Account <span class="text-danger">*</span></label>
                    <select name="bank_account_id" class="form-select" required>
                        <option value="">Select Cash or Bank Account...</option>
                        @foreach($bankAccounts as $acc)
                            <option value="{{ $acc->id }}">
                                {{ $acc->code }} - {{ $acc->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text small">Select the internal general ledger bank account to reconcile.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Bank Statement Ending Date <span class="text-danger">*</span></label>
                    <input type="date" name="statement_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    <div class="form-text small">The cut-off closing date listed on the physical or electronic bank statement.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Statement Ending Balance ($) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" step="0.01" name="statement_balance" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="form-text small">The ending balance exactly as printed on your official bank statement.</div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-semibold">Session Notes (Optional)</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. October 2026 Monthly Bank Reconciliation"></textarea>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('bank-reconciliations.index') }}" class="btn btn-secondary btn-sm">Cancel</a>
                    <button type="submit" class="btn btn-primary btn-sm fw-semibold">
                        <i class="fa-solid fa-play me-1"></i> Open Reconciliation Worksheet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
