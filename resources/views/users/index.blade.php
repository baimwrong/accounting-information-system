@extends('layouts.app')

@section('title', 'User Administration')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h3 class="fw-bold mb-1">User Administration & RBAC Permissions</h3>
        <p class="text-muted mb-0 small">Manage internal personnel access, role-based capabilities, and authentication credentials</p>
    </div>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newUserModal">
        <i class="fa-solid fa-user-plus me-1"></i> Add System User
    </button>
</div>

<!-- Search & Filter Card -->
<div class="card p-3 mb-4 shadow-sm border">
    <form method="GET" action="{{ route('users.index') }}" class="row g-2 align-items-center">
        <div class="col-md-5">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                <input type="text" name="search" class="form-control" placeholder="Search by name or email..." value="{{ request('search') }}">
            </div>
        </div>
        <div class="col-md-3">
            <select name="role" class="form-select form-select-sm">
                <option value="">All Roles</option>
                <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Administrator</option>
                <option value="accountant" {{ request('role') === 'accountant' ? 'selected' : '' }}>Accountant</option>
                <option value="auditor" {{ request('role') === 'auditor' ? 'selected' : '' }}>Auditor</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                <option value="deleted" {{ request('status') === 'deleted' ? 'selected' : '' }}>Deactivated / Trashed</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
            <a href="{{ route('users.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </form>
</div>

<div class="card shadow-sm border">
    <div class="table-responsive">
        <table class="table table-hover table-finance mb-0">
            <thead>
                <tr>
                    <th>User / Name</th>
                    <th>Email Address</th>
                    <th>Role</th>
                    <th>Account Status</th>
                    <th>Joined</th>
                    <th class="text-end" style="width: 180px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $u)
                <tr class="{{ $u->trashed() ? 'table-light text-muted' : '' }}">
                    <td>
                        <div class="fw-bold">{{ $u->name }}</div>
                        @if($u->id === auth()->user()->id)
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle small" style="font-size: 0.65rem;">YOU</span>
                        @endif
                    </td>
                    <td class="font-monospace small">{{ $u->email }}</td>
                    <td>
                        @if($u->role === 'admin')
                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger">Admin</span>
                        @elseif($u->role === 'accountant')
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary">Accountant</span>
                        @elseif($u->role === 'auditor')
                            <span class="badge bg-info bg-opacity-10 text-info border border-info">Auditor</span>
                        @else
                            <span class="badge bg-secondary text-uppercase">{{ $u->role }}</span>
                        @endif
                    </td>
                    <td>
                        @if($u->trashed())
                            <span class="badge bg-dark">DEACTIVATED</span>
                        @elseif($u->is_active)
                            <span class="badge bg-success bg-opacity-10 text-success border border-success">ACTIVE</span>
                        @else
                            <span class="badge bg-warning bg-opacity-10 text-warning border border-warning">INACTIVE</span>
                        @endif
                    </td>
                    <td>{{ $u->created_at->format('M d, Y') }}</td>
                    <td class="text-end">
                        @if($u->trashed())
                            <form action="{{ route('users.restore', $u->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-success btn-sm" title="Restore User">
                                    <i class="fa-solid fa-trash-arrow-up me-1"></i> Restore
                                </button>
                            </form>
                        @else
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editUserModal{{ $u->id }}" title="Edit User">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <button type="button" class="btn btn-outline-warning btn-sm text-dark" data-bs-toggle="modal" data-bs-target="#resetPasswordModal{{ $u->id }}" title="Reset Password">
                                <i class="fa-solid fa-key"></i>
                            </button>
                            @if($u->id !== auth()->user()->id)
                            <form action="{{ route('users.destroy', $u) }}" method="POST" class="d-inline" onsubmit="return confirm('Deactivate {{ $u->name }}? The user will immediately be barred from logging in.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Deactivate User">
                                    <i class="fa-solid fa-user-slash"></i>
                                </button>
                            </form>
                            @endif
                        @endif
                    </td>
                </tr>

                @if(!$u->trashed())
                <!-- Edit User Modal -->
                <div class="modal fade" id="editUserModal{{ $u->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form action="{{ route('users.update', $u) }}" method="POST">
                                @csrf
                                @method('PUT')
                                <div class="modal-header">
                                    <h5 class="modal-title fs-6 fw-bold">Edit User: {{ $u->name }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
                                        <input type="text" name="name" class="form-control" value="{{ $u->name }}" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Email Address <span class="text-danger">*</span></label>
                                        <input type="email" name="email" class="form-control" value="{{ $u->email }}" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Role <span class="text-danger">*</span></label>
                                        <select name="role" class="form-select" required {{ $u->id === auth()->user()->id ? 'disabled' : '' }}>
                                            <option value="admin" {{ $u->role === 'admin' ? 'selected' : '' }}>System Administrator (Full access)</option>
                                            <option value="accountant" {{ $u->role === 'accountant' ? 'selected' : '' }}>Senior Accountant (Operations & Financials)</option>
                                            <option value="auditor" {{ $u->role === 'auditor' ? 'selected' : '' }}>Compliance Auditor (Read-only reports & GL)</option>
                                        </select>
                                        @if($u->id === auth()->user()->id)
                                            <input type="hidden" name="role" value="admin">
                                            <div class="small text-muted mt-1">You cannot modify your own administrator role.</div>
                                        @endif
                                    </div>
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" name="is_active" id="isActiveSwitch{{ $u->id }}" value="1" {{ $u->is_active ? 'checked' : '' }} {{ $u->id === auth()->user()->id ? 'disabled' : '' }}>
                                        <label class="form-check-label small fw-semibold" for="isActiveSwitch{{ $u->id }}">Account Active / Permitted to Sign In</label>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Reset Password Modal -->
                <div class="modal fade" id="resetPasswordModal{{ $u->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form action="{{ route('users.reset-password', $u) }}" method="POST">
                                @csrf
                                <div class="modal-header bg-warning text-dark">
                                    <h5 class="modal-title fs-6 fw-bold">Reset Password: {{ $u->email }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="small text-muted mb-3">Set a new secure password for this user. The user will be required to authenticate with these new credentials on their next session.</p>
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">New Password (Min 8 chars) <span class="text-danger">*</span></label>
                                        <input type="password" name="password" class="form-control" required minlength="8">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                                        <input type="password" name="password_confirmation" class="form-control" required minlength="8">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-warning btn-sm fw-semibold">Set New Password</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @endif
                @empty
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">No users found matching search criteria.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($users->hasPages())
    <div class="p-3 border-top">
        {{ $users->links() }}
    </div>
    @endif
</div>

<!-- New User Modal -->
<div class="modal fade" id="newUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('users.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fs-6 fw-bold"><i class="fa-solid fa-user-plus me-1 text-primary"></i> Create System User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Full Individual Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Jane Doe" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Corporate Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="e.g. jdoe@internal.network" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Security Role <span class="text-danger">*</span></label>
                        <select name="role" class="form-select" required>
                            <option value="accountant" selected>Senior Accountant (COA, Journals, Invoices, Bills, Financial Reports)</option>
                            <option value="auditor">Compliance Auditor (Read-Only inspection of GL, Financials, Audit Logs)</option>
                            <option value="admin">System Administrator (Full access, Period locks, Bulk Import, User Admin)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Initial Password (Min 8 chars) <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required minlength="8" placeholder="Enter secure password">
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="is_active" id="newIsActive" value="1" checked>
                        <label class="form-check-label small fw-semibold" for="newIsActive">Enable Account Immediately</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check me-1"></i> Provision User</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
