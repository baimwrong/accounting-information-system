@extends('layouts.app')

@section('title', 'Chart of Accounts')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h3 class="fw-bold mb-1">Chart of Accounts (COA)</h3>
        <p class="text-muted mb-0 small">Structural foundation of General Ledger classifications and financial accounts</p>
    </div>
    <div class="d-flex gap-2">
        @if(auth()->user()->isAccountant())
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newAccountModal">
            <i class="fa-solid fa-folder-plus me-1"></i> New Account
        </button>
        @endif
        @if(auth()->user()->isAdmin())
        <a href="{{ route('imports.index') }}" class="btn btn-outline-warning btn-sm text-dark">
            <i class="fa-solid fa-file-arrow-up me-1"></i> Import COA (CSV)
        </a>
        @endif
    </div>
</div>

<!-- Filters and Search -->
<div class="card mb-4">
    <div class="card-body p-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="btn-group btn-group-sm" role="group">
                <a href="{{ route('accounts.index') }}" class="btn {{ !request('type') ? 'btn-primary' : 'btn-outline-secondary' }}">All</a>
                <a href="{{ route('accounts.index', ['type' => 'asset']) }}" class="btn {{ request('type') == 'asset' ? 'btn-primary' : 'btn-outline-secondary' }}">Assets</a>
                <a href="{{ route('accounts.index', ['type' => 'liability']) }}" class="btn {{ request('type') == 'liability' ? 'btn-primary' : 'btn-outline-secondary' }}">Liabilities</a>
                <a href="{{ route('accounts.index', ['type' => 'equity']) }}" class="btn {{ request('type') == 'equity' ? 'btn-primary' : 'btn-outline-secondary' }}">Equity</a>
                <a href="{{ route('accounts.index', ['type' => 'revenue']) }}" class="btn {{ request('type') == 'revenue' ? 'btn-primary' : 'btn-outline-secondary' }}">Revenue</a>
                <a href="{{ route('accounts.index', ['type' => 'expense']) }}" class="btn {{ request('type') == 'expense' ? 'btn-primary' : 'btn-outline-secondary' }}">Expenses</a>
            </div>

            <form method="GET" action="{{ route('accounts.index') }}" class="d-flex gap-2" style="max-width: 300px;">
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

<!-- Accounts Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-finance mb-0">
            <thead>
                <tr>
                    <th style="width: 120px;">Code</th>
                    <th>Account Name</th>
                    <th>Type</th>
                    <th>Sub Type</th>
                    <th>Normal Balance</th>
                    <th class="num-col">Current Balance</th>
                    <th>Status</th>
                    <th class="text-end" style="width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($accounts as $acc)
                <tr>
                    <td class="fw-bold font-monospace text-primary">
                        {{ $acc->code }}
                    </td>
                    <td>
                        <div class="fw-semibold">{{ $acc->name }}</div>
                        @if($acc->parent)
                            <div class="text-muted small"><i class="fa-solid fa-turn-up fa-rotate-90 me-1"></i> Sub of {{ $acc->parent->code }} - {{ $acc->parent->name }}</div>
                        @endif
                    </td>
                    <td>
                        @switch($acc->type)
                            @case('asset')
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary">Asset</span>
                                @break
                            @case('liability')
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger">Liability</span>
                                @break
                            @case('equity')
                                <span class="badge bg-success bg-opacity-10 text-success border border-success">Equity</span>
                                @break
                            @case('revenue')
                                <span class="badge bg-info bg-opacity-10 text-info border border-info">Revenue</span>
                                @break
                            @case('expense')
                                <span class="badge bg-warning bg-opacity-10 text-warning border border-warning">Expense</span>
                                @break
                        @endswitch
                    </td>
                    <td class="text-muted small text-capitalize">{{ str_replace('_', ' ', $acc->sub_type ?? '-') }}</td>
                    @php
                        $displayBalance = $acc->current_balance ?? $acc->balance;
                    @endphp
                    <td class="num-col fw-bold {{ $displayBalance < 0 ? 'text-danger' : 'text-dark' }}">
                        ${{ number_format($displayBalance, 2) }}
                    </td>
                    <td>
                        @if($acc->is_active)
                            <span class="badge bg-success-subtle text-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <a href="{{ route('accounts.statement', $acc) }}" class="btn btn-outline-info" title="View General Ledger Statement">
                                <i class="fa-solid fa-list-check"></i>
                            </a>
                            @if(auth()->user()->isAccountant())
                            <a href="{{ route('accounts.edit', $acc) }}" class="btn btn-outline-secondary" title="Edit">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </a>
                            @endif
                            @if(auth()->user()->isAdmin() && !$acc->is_system)
                            <form action="{{ route('accounts.destroy', $acc) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete account {{ $acc->code }}?');" class="d-inline">
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
                    <td colspan="8" class="text-center text-muted py-4">No accounts found matching criteria.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($accounts->hasPages())
    <div class="card-footer bg-white py-3">
        {{ $accounts->links() }}
    </div>
    @endif
</div>

<!-- New Account Modal -->
@if(auth()->user()->isAccountant())
<div class="modal fade" id="newAccountModal" tabindex="-1" aria-labelledby="newAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('accounts.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="newAccountModalLabel"><i class="fa-solid fa-folder-plus text-primary me-2"></i>Create New Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small">Account Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control" placeholder="e.g. 1050" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small">Classification <span class="text-danger">*</span></label>
                            <select name="type" class="form-select" id="accountTypeSelect" required>
                                <option value="asset">Asset</option>
                                <option value="liability">Liability</option>
                                <option value="equity">Equity</option>
                                <option value="revenue">Revenue</option>
                                <option value="expense">Expense</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Account Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Corporate Checking Account" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small">Sub-Type</label>
                            <input type="text" name="sub_type" class="form-control" placeholder="e.g. current_asset">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold small">Normal Balance <span class="text-danger">*</span></label>
                            <select name="normal_balance" class="form-select" id="normalBalanceSelect" required>
                                <option value="debit">Debit</option>
                                <option value="credit">Credit</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Parent Account (Optional)</label>
                            <select name="parent_id" class="form-select">
                                <option value="">-- None (Top Level) --</option>
                                @foreach($parentAccounts as $parent)
                                    <option value="{{ $parent->id }}">{{ $parent->code }} - {{ $parent->name }} ({{ ucfirst($parent->type) }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Description / Notes</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Account purpose and usage guideline..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-semibold">Save Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById('accountTypeSelect')?.addEventListener('change', function () {
        const val = this.value;
        const norm = document.getElementById('normalBalanceSelect');
        if (val === 'asset' || val === 'expense') {
            norm.value = 'debit';
        } else {
            norm.value = 'credit';
        }
    });
</script>
@endif
@endsection
