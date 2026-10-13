@extends('layouts.guest')

@section('title', 'Sign In')

@section('content')
<div class="p-4 p-md-5">
    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle mb-3" style="width: 64px; height: 64px;">
            <i class="fa-solid fa-chart-pie fs-2"></i>
        </div>
        <h4 class="fw-bold mb-1">ApexAIS</h4>
        <p class="text-muted small">Enterprise Accounting Information System</p>
    </div>

    @if (session('status'))
        <div class="alert alert-info py-2 small mb-3">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger py-2 small mb-3">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('login.post') }}">
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label small fw-semibold">Work Email Address</label>
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="fa-regular fa-envelope text-muted"></i></span>
                <input type="email" class="form-control" id="email" name="email" value="{{ old('email', 'admin@example.com') }}" required autofocus placeholder="name@company.com">
            </div>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label small fw-semibold">Password</label>
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="fa-solid fa-lock text-muted"></i></span>
                <input type="password" class="form-control" id="password" name="password" required value="password" placeholder="••••••••">
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" checked>
                <label class="form-check-label small text-muted" for="remember">
                    Remember me
                </label>
            </div>
            <span class="badge bg-light text-muted border">Local Intranet</span>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
            <i class="fa-solid fa-arrow-right-to-bracket me-2"></i> Sign In to Dashboard
        </button>
    </form>

    <div class="mt-4 pt-3 border-top text-center">
        <div class="small text-muted mb-2">Default Demonstration Accounts:</div>
        <div class="badge bg-light text-dark border p-2 text-start w-100 small">
            <div><strong>Admin:</strong> admin@example.com / password</div>
            <div><strong>Accountant:</strong> accountant@example.com / password</div>
            <div><strong>Auditor:</strong> auditor@example.com / password</div>
        </div>
    </div>
</div>
@endsection
