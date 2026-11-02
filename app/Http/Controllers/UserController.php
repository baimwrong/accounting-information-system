<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::withTrashed()->orderBy('name');

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'active') {
                $query->whereNull('deleted_at')->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->whereNull('deleted_at')->where('is_active', false);
            } elseif ($status === 'deleted') {
                $query->onlyTrashed();
            }
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        $users = $query->paginate(15)->withQueryString();

        return view('users.index', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:admin,accountant,auditor'],
            'password' => ['required', 'string', 'min:8'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'password' => Hash::make($data['password']),
            'is_active' => $request->boolean('is_active', true),
        ]);

        AuditLog::record(
            $request->user(),
            'create_user',
            "Created user account {$user->email} with role '{$user->role}'",
            $user
        );

        return redirect()->route('users.index')
            ->with('success', "User account {$user->name} ({$user->email}) created successfully.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'role' => ['required', 'in:admin,accountant,auditor'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // Prevent self-demotion or self-deactivation
        if ($user->id === $request->user()->id) {
            if ($data['role'] !== 'admin') {
                return back()->with('error', 'You cannot remove administrative privileges from your own active account.');
            }
            if (! $request->boolean('is_active', true)) {
                return back()->with('error', 'You cannot deactivate your own active account.');
            }
        }

        $oldRole = $user->role;
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->role = $data['role'];
        $user->is_active = $request->boolean('is_active', true);
        $user->save();

        AuditLog::record(
            $request->user(),
            'update_user',
            "Updated user {$user->email} (Role: {$oldRole} -> {$user->role}, Active: ".($user->is_active ? 'Yes' : 'No').')',
            $user
        );

        return redirect()->route('users.index')
            ->with('success', "User account {$user->name} updated successfully.");
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->password = Hash::make($data['password']);
        $user->save();

        AuditLog::record(
            $request->user(),
            'reset_user_password',
            "Reset credentials for user {$user->email}",
            $user
        );

        return back()->with('success', "Password for user {$user->email} has been reset successfully.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot delete your own active administrator account.');
        }

        $email = $user->email;
        $user->delete();

        AuditLog::record(
            $request->user(),
            'delete_user',
            "Deactivated and soft-deleted user account {$email}",
            $user
        );

        return redirect()->route('users.index')
            ->with('success', "User {$email} has been deactivated.");
    }

    public function restore(Request $request, int $id): RedirectResponse
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $user->restore();
        $user->is_active = true;
        $user->save();

        AuditLog::record(
            $request->user(),
            'restore_user',
            "Restored user account {$user->email}",
            $user
        );

        return redirect()->route('users.index')
            ->with('success', "User {$user->email} has been restored and activated.");
    }
}
