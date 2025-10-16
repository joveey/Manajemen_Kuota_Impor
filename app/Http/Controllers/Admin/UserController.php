<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules;

class UserController extends Controller
{
    /**
     * Display a listing of users (non-admin users).
     */
    public function index()
    {
        // Query dasar: hanya pengguna non-admin dan bukan akun saat ini
        $baseQuery = User::whereDoesntHave('roles', function ($query) {
            $query->where('name', 'admin');
        })
        ->where('id', '!=', auth()->id());

        $users = (clone $baseQuery)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)->where('is_active', true)->count(),
            'inactive' => (clone $baseQuery)->where('is_active', false)->count(),
        ];
        
        return view('admin.users.index', compact('users', 'stats'));
    }

    /**
     * Show the form for creating a new user.
     */
    public function create()
    {
        // Hanya Admin dan Editor yang boleh memilih role selain default
        if (auth()->user()->hasRole(['admin', 'editor'])) {
            // Get roles kecuali admin role
            $roles = Role::where('name', '!=', 'admin')->orderBy('name', 'asc')->get();
        } else {
            // Manager atau selainnya tidak bisa memilih role; fallback ke role default
            $roles = collect();
        }
        
        return view('admin.users.create', compact('roles'));
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'is_active' => 'nullable|in:0,1',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_active' => $request->input('is_active', 0) == 1,
        ]);

        // Assign roles: hanya Admin/Editor dapat set role selain default
        if (auth()->user()->hasRole(['admin', 'editor'])) {
            if ($request->has('roles')) {
                $roleIds = Role::whereIn('id', $request->roles)
                    ->where('name', '!=', 'admin')
                    ->pluck('id')
                    ->toArray();
                $user->roles()->sync($roleIds);
            }
        } else {
            // Untuk selain Admin/Editor, set role default "user"
            try {
                $user->assignRole('user');
            } catch (\Throwable $e) {
                // ignore when seeding not ready
            }
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        // Prevent viewing admin users
        if ($user->isAdmin()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Cannot view admin user details here.');
        }

        $user->load('roles.permissions');
        
        return view('admin.users.show', compact('user'));
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user)
    {
        // Prevent editing admin users
        if ($user->isAdmin()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Cannot edit admin user here.');
        }

        if (auth()->user()->hasRole(['admin', 'editor'])) {
            $roles = Role::where('name', '!=', 'admin')->orderBy('name', 'asc')->get();
            $userRoles = $user->roles->pluck('id')->toArray();
        } else {
            $roles = collect();
            $userRoles = [];
        }
        
        return view('admin.users.edit', compact('user', 'roles', 'userRoles'));
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, User $user)
    {
        // Prevent updating admin users
        if ($user->isAdmin()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Cannot edit admin user here.');
        }

        // Validasi rules
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'is_active' => 'nullable|in:0,1',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,id',
        ];

        // Hanya validasi password jika diisi
        if ($request->filled('password')) {
            $rules['password'] = ['required', 'confirmed', Rules\Password::defaults()];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $userData = [
            'name' => $request->name,
            'email' => $request->email,
            'is_active' => $request->input('is_active', 0) == 1,
        ];

        // Update password hanya jika diisi
        if ($request->filled('password')) {
            $userData['password'] = Hash::make($request->password);
        }

        $user->update($userData);

        // Sync roles hanya jika Admin/Editor; lainnya tidak diizinkan mengubah role
        if (auth()->user()->hasRole(['admin', 'editor'])) {
            if ($request->has('roles')) {
                $roleIds = Role::whereIn('id', $request->roles)
                    ->where('name', '!=', 'admin')
                    ->pluck('id')
                    ->toArray();
                $user->roles()->sync($roleIds);
            } else {
                $user->roles()->sync([]);
            }
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully. ' . ($request->filled('password') ? 'Password has been changed.' : 'Password unchanged.'));
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(User $user)
    {
        // Prevent deleting admin users
        if ($user->isAdmin()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Cannot delete admin user. Please change to regular user first.');
        }

        // Prevent deleting current user
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Cannot delete your own account.');
        }

        // Detach all roles
        $user->roles()->detach();
        
        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }
}
