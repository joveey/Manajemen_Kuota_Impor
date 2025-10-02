<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules;

class AdminController extends Controller
{
    /**
     * Apply middleware to ensure user is authenticated
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of admin users.
     */
    public function index()
    {
        // Get users yang punya admin role
        $admins = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })
        ->orderBy('created_at', 'desc')
        ->paginate(10);
        
        return view('admin.admins.index', compact('admins'));
    }

    /**
     * Show the form for creating a new admin.
     */
    public function create()
    {
        $roles = Role::orderBy('name', 'asc')->get();
        
        return view('admin.admins.create', compact('roles'));
    }

    /**
     * Store a newly created admin in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'is_active' => 'boolean',
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
            'is_active' => $request->has('is_active') ? true : false,
        ]);

        // Assign admin role
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $user->roles()->attach($adminRole->id);
        }

        // Assign additional roles if provided
        if ($request->has('roles')) {
            $user->roles()->syncWithoutDetaching($request->roles);
        }

        return redirect()->route('admin.admins.index')
            ->with('success', 'Admin created successfully.');
    }

    /**
     * Display the specified admin.
     */
    public function show(User $admin)
    {
        // Verify user is admin
        if (!$admin->isAdmin()) {
            return redirect()->route('admin.admins.index')
                ->with('error', 'User is not an admin.');
        }

        $admin->load('roles.permissions');
        
        return view('admin.admins.show', compact('admin'));
    }

    /**
     * Show the form for editing the specified admin.
     */
    public function edit(User $admin)
    {
        // Verify user is admin
        if (!$admin->isAdmin()) {
            return redirect()->route('admin.admins.index')
                ->with('error', 'User is not an admin.');
        }

        $roles = Role::orderBy('name', 'asc')->get();
        $adminRoles = $admin->roles->pluck('id')->toArray();
        
        return view('admin.admins.edit', compact('admin', 'roles', 'adminRoles'));
    }

    /**
     * Update the specified admin in storage.
     */
    public function update(Request $request, User $admin)
    {
        // Verify user is admin
        if (!$admin->isAdmin()) {
            return redirect()->route('admin.admins.index')
                ->with('error', 'User is not an admin.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $admin->id,
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'is_active' => 'boolean',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $userData = [
            'name' => $request->name,
            'email' => $request->email,
            'is_active' => $request->has('is_active') ? true : false,
        ];

        // Update password jika diisi
        if ($request->filled('password')) {
            $userData['password'] = Hash::make($request->password);
        }

        $admin->update($userData);

        // Sync roles (pastikan admin role tetap ada)
        $adminRole = Role::where('name', 'admin')->first();
        $roleIds = $request->has('roles') ? $request->roles : [];
        
        if ($adminRole && !in_array($adminRole->id, $roleIds)) {
            $roleIds[] = $adminRole->id;
        }
        
        $admin->roles()->sync($roleIds);

        return redirect()->route('admin.admins.index')
            ->with('success', 'Admin updated successfully.');
    }

    /**
     * Convert admin to regular user (remove admin role).
     */
    public function convertToUser(User $admin)
    {
        // Verify user is admin
        if (!$admin->isAdmin()) {
            return redirect()->route('admin.admins.index')
                ->with('error', 'User is not an admin.');
        }

        // Prevent converting current user
        if ($admin->id === auth()->id()) {
            return redirect()->route('admin.admins.index')
                ->with('error', 'Cannot convert your own admin account.');
        }

        // Remove admin role
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $admin->roles()->detach($adminRole->id);
        }

        return redirect()->route('admin.admins.index')
            ->with('success', 'Admin converted to regular user successfully. You can now delete this user from Users page.');
    }

    /**
     * Remove the specified admin from storage.
     * Note: This should not be directly accessible. Admins must be converted to users first.
     */
    public function destroy(User $admin)
    {
        // Prevent deleting admin users directly
        if ($admin->isAdmin()) {
            return redirect()->route('admin.admins.index')
                ->with('error', 'Cannot delete admin directly. Please convert to regular user first.');
        }

        return redirect()->route('admin.admins.index')
            ->with('error', 'This user is not an admin.');
    }
}
