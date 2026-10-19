@extends('layouts.app')

@section('title', 'User Profile')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex align-items-center gap-2 py-3">
                <i class="fa-solid fa-user-gear text-primary fs-5"></i>
                <h5 class="card-title mb-0 fw-bold">User Account Settings</h5>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('profile.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Full Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email Address</label>
                        <input type="email" class="form-control" value="{{ $user->email }}" disabled readonly>
                        <div class="form-text">Email address is managed by the system administrator.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Assigned Role</label>
                        <div>
                            <span class="badge bg-primary text-uppercase px-3 py-2 fs-6">{{ $user->role }}</span>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h6 class="fw-bold mb-3">Change Password <span class="text-muted fw-normal small">(Leave blank to keep current)</span></h6>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Current Password</label>
                        <input type="password" name="current_password" class="form-control" placeholder="••••••••">
                        <div class="form-text">Required only when updating your password.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Password</label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Confirm New Password</label>
                        <input type="password" name="password_confirmation" class="form-control" placeholder="••••••••">
                    </div>

                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Profile Changes
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
