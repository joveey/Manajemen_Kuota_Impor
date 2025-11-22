<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
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
        ->where('id', '!=', Auth::id());

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
     * Display recently created users with roles.
     */
    public function recent()
    {
        $recentUsers = User::with('roles')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('admin.users.recent', compact('recentUsers'));
    }

    /**
     * Show the form for creating a new user.
     */
    public function create()
    {
        /** @var \App\Models\User $currentUser */
        $currentUser = Auth::user();

        // Only Admin and Editor may choose roles other than the default
        if ($currentUser->hasRole(['admin', 'editor'])) {
            // Admin can see all roles including admin; Editor sees all except admin
            $q = Role::query();
            if (!$currentUser->hasRole('admin')) { $q->where('name', '!=', 'admin'); }
            $roles = $q->orderBy('name', 'asc')->get();
        } else {
            // Manager or others cannot choose roles; fallback to the default role
            $roles = collect();
        }
        
        return view('admin.users.create', compact('roles'));
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request)
    {
        /** @var \App\Models\User $currentUser */
        $currentUser = Auth::user();

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

        // Assign roles: Admin/Editor can set roles; Editor cannot set admin
        if ($currentUser->hasRole(['admin', 'editor'])) {
            if ($request->has('roles')) {
                $q = Role::whereIn('id', $request->roles);
                if (!$currentUser->hasRole('admin')) { $q->where('name', '!=', 'admin'); }
                $roleIds = $q->pluck('id')->toArray();
                $user->roles()->sync($roleIds);
            }
        } else {
            // For non Admin/Editor, apply the default "user" role
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
        /** @var \App\Models\User $currentUser */
        $currentUser = Auth::user();

        // Prevent viewing admin users kecuali current user adalah admin
        if ($user->isAdmin() && !$currentUser->hasRole('admin')) {
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
        /** @var \App\Models\User $currentUser */
        $currentUser = Auth::user();

        // Prevent editing admin users kecuali current user adalah admin
        if ($user->isAdmin() && !$currentUser->hasRole('admin')) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Cannot edit admin user here.');
        }

        if ($currentUser->hasRole(['admin', 'editor'])) {
            $q = Role::query();
            if (!$currentUser->hasRole('admin')) { $q->where('name', '!=', 'admin'); }
            $roles = $q->orderBy('name', 'asc')->get();
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
        /** @var \App\Models\User $currentUser */
        $currentUser = Auth::user();

        // Prevent updating admin users kecuali current user adalah admin
        if ($user->isAdmin() && !$currentUser->hasRole('admin')) {
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

        // Update password only if provided
        if ($request->filled('password')) {
            $userData['password'] = Hash::make($request->password);
        }

        $user->update($userData);

        // Sync roles only for Admin/Editor; Editor cannot set admin
        if ($currentUser->hasRole(['admin', 'editor'])) {
            if ($request->has('roles')) {
                $q = Role::whereIn('id', $request->roles);
                if (!$currentUser->hasRole('admin')) { $q->where('name', '!=', 'admin'); }
                $roleIds = $q->pluck('id')->toArray();
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
        /** @var \App\Models\User $currentUser */
        $currentUser = Auth::user();

        // Prevent deleting admin users kecuali current user adalah admin
        if ($user->isAdmin() && !$currentUser->hasRole('admin')) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Cannot delete admin user. Please change to regular user first.');
        }

        // Prevent deleting current user
        if ($user->id === Auth::id()) {
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
