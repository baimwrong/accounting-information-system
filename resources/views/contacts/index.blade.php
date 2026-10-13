@extends('layouts.app')

@section('title', 'Contacts (Customers & Vendors)')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h3 class="fw-bold mb-1">Contacts Directory</h3>
        <p class="text-muted mb-0 small">Accounts Receivable Customers and Accounts Payable Vendors</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('contacts.create') }}" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-user-plus me-1"></i> New Contact
        </a>
        @if(auth()->user()->isAdmin())
        <a href="{{ route('imports.index') }}" class="btn btn-outline-warning btn-sm text-dark">
            <i class="fa-solid fa-file-arrow-up me-1"></i> Import Contacts (CSV)
        </a>
        @endif
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body p-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="btn-group btn-group-sm">
                <a href="{{ route('contacts.index') }}" class="btn {{ !request('type') ? 'btn-primary' : 'btn-outline-secondary' }}">All</a>
                <a href="{{ route('contacts.index', ['type' => 'customer']) }}" class="btn {{ request('type') == 'customer' ? 'btn-primary' : 'btn-outline-secondary' }}">Customers</a>
                <a href="{{ route('contacts.index', ['type' => 'vendor']) }}" class="btn {{ request('type') == 'vendor' ? 'btn-primary' : 'btn-outline-secondary' }}">Vendors</a>
            </div>

            <form method="GET" action="{{ route('contacts.index') }}" class="d-flex gap-2" style="max-width: 320px;">
                @if(request('type'))
                    <input type="hidden" name="type" value="{{ request('type') }}">
                @endif
                <div class="input-group input-group-sm">
                    <input type="text" name="search" class="form-control" placeholder="Search name, email, phone..." value="{{ request('search') }}">
                    <button class="btn btn-outline-secondary" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Contacts Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-finance mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Company</th>
                    <th>Type</th>
                    <th>Email / Phone</th>
                    <th class="num-col">Outstanding Balance</th>
                    <th>Status</th>
                    <th class="text-end" style="width: 120px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($contacts as $c)
                <tr>
                    <td>
                        <a href="{{ route('contacts.show', $c) }}" class="fw-bold text-decoration-none text-dark">
                            {{ $c->name }}
                        </a>
                    </td>
                    <td>{{ $c->company_name ?: '-' }}</td>
                    <td>
                        @if($c->type === 'customer')
                            <span class="badge bg-success bg-opacity-10 text-success border border-success">Customer</span>
                        @elseif($c->type === 'vendor')
                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger">Vendor</span>
                        @else
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary">Both</span>
                        @endif
                    </td>
                    <td>
                        <div>{{ $c->email ?: '-' }}</div>
                        <div class="small text-muted">{{ $c->phone ?: '' }}</div>
                    </td>
                    <td class="num-col fw-bold">
                        ${{ number_format($c->outstanding_balance, 2) }}
                    </td>
                    <td>
                        @if($c->is_active)
                            <span class="badge bg-success-subtle text-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <a href="{{ route('contacts.show', $c) }}" class="btn btn-outline-primary" title="View">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            <a href="{{ route('contacts.edit', $c) }}" class="btn btn-outline-secondary" title="Edit">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">No contacts found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($contacts->hasPages())
    <div class="card-footer bg-white py-3">
        {{ $contacts->links() }}
    </div>
    @endif
</div>
@endsection
