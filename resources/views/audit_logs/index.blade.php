@extends('layouts.app')

@section('title', 'Audit Trail')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h3 class="fw-bold mb-1"><i class="fa-solid fa-shield-halved text-primary me-2"></i>System Audit Trail</h3>
        <p class="text-muted mb-0 small">Immutable chronological activity logs recording all financial actions, postings, and bulk operations</p>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('audit-logs.index') }}" class="row g-2 align-items-center">
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">Action</span>
                    <select name="action" class="form-select">
                        <option value="">All Actions</option>
                        <option value="create_journal_entry" {{ request('action') === 'create_journal_entry' ? 'selected' : '' }}>Create Journal Entry</option>
                        <option value="post_journal_entry" {{ request('action') === 'post_journal_entry' ? 'selected' : '' }}>Post Journal Entry</option>
                        <option value="void_journal_entry" {{ request('action') === 'void_journal_entry' ? 'selected' : '' }}>Void Journal Entry</option>
                        <option value="create_invoice" {{ request('action') === 'create_invoice' ? 'selected' : '' }}>Create Invoice</option>
                        <option value="record_payment" {{ request('action') === 'record_payment' ? 'selected' : '' }}>Record Payment</option>
                        <option value="create_bill" {{ request('action') === 'create_bill' ? 'selected' : '' }}>Create Bill</option>
                        <option value="bulk_import" {{ request('action') === 'bulk_import' ? 'selected' : '' }}>Bulk Import</option>
                        <option value="login" {{ request('action') === 'login' ? 'selected' : '' }}>User Login</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search description, IP, or user..." value="{{ request('search') }}">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filter Logs</button>
                <a href="{{ route('audit-logs.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Logs Table -->
<div class="card shadow-sm border">
    <div class="table-responsive">
        <table class="table table-hover table-finance mb-0">
            <thead>
                <tr>
                    <th style="width: 170px;">Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Entity Type</th>
                    <th>Audit Description</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td class="font-monospace small text-muted">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                    <td>
                        <span class="fw-semibold">{{ $log->user ? $log->user->name : 'System' }}</span>
                        @if($log->user)
                            <div class="small text-muted">{{ $log->user->email }}</div>
                        @endif
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border font-monospace small">
                            {{ $log->action }}
                        </span>
                    </td>
                    <td>
                        @if($log->model_type)
                            <span class="badge bg-secondary-subtle text-dark small font-monospace">
                                {{ class_basename($log->model_type) }} #{{ $log->model_id }}
                            </span>
                        @else
                            <span class="text-muted small">-</span>
                        @endif
                    </td>
                    <td>{{ $log->description }}</td>
                    <td class="font-monospace small text-muted">{{ $log->ip_address ?: '-' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">No audit logs recorded matching search criteria.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($logs->hasPages())
    <div class="card-footer bg-white py-3">
        {{ $logs->links() }}
    </div>
    @endif
</div>
@endsection
