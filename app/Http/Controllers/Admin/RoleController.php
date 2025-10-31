<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:read roles')->only(['index', 'show']);
        $this->middleware('permission:create roles')->only(['create', 'store']);
        $this->middleware('permission:update roles')->only(['edit', 'update']);
        $this->middleware('permission:delete roles')->only(['destroy']);
    }
    /**
     * Display a listing of the roles.
     */
    public function index()
    {
        $roles = Role::withCount(['permissions', 'users'])
            ->orderBy('name', 'asc')
            ->paginate(10);
        
        return view('admin.roles.index', compact('roles'));
    }

    /**
     * Show the form for creating a new role.
     */
    public function create()
    {
        // Guard by permission via Gate (admin bypass respected); middleware already enforces
        if (!auth()->user()->can('create roles')) {
            return redirect()->route('admin.roles.index')
                ->with('error', 'You do not have permission to create roles.');
        }

        $permissions = Permission::orderBy('name', 'asc')->get();
        
        return view('admin.roles.create', compact('permissions'));
    }

    /**
     * Store a newly created role in storage.
     */
    public function store(Request $request)
    {
        // Guard by permission via Gate (admin bypass respected); middleware already enforces
        if (!auth()->user()->can('create roles')) {
            return redirect()->route('admin.roles.index')
                ->with('error', 'You do not have permission to create roles.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:roles,name',
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $role = Role::create([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        // Attach permissions to role
        if ($request->has('permissions')) {
            $role->permissions()->sync($request->permissions);
        }

        return redirect()->route('admin.roles.index')
            ->with('success', 'Role created successfully.');
    }

    /**
     * Display the specified role.
     */
    public function show(Role $role)
    {
        $role->load('permissions');
        $users = $role->users()->paginate(10);
        
        return view('admin.roles.show', compact('role', 'users'));
    }

    /**
     * Show the form for editing the specified role.
     */
    public function edit(Role $role)
    {
        // Guard by permission via Gate (admin bypass respected); middleware already enforces
        if (!auth()->user()->can('update roles')) {
            return redirect()->route('admin.roles.index')
                ->with('error', 'You do not have permission to edit roles.');
        }

        // Prevent non-admin from editing admin role
        if ($role->name === 'admin' && !auth()->user()->hasRole('admin')) {
            return redirect()->route('admin.roles.index')
                ->with('error', 'You cannot modify the Admin role.');
        }

        $permissions = Permission::orderBy('name', 'asc')->get();
        $rolePermissions = $role->permissions->pluck('id')->toArray();
        
        return view('admin.roles.edit', compact('role', 'permissions', 'rolePermissions'));
    }

    /**
     * Update the specified role in storage.
     */
    public function update(Request $request, Role $role)
    {
        // Guard by permission via Gate (admin bypass respected); middleware already enforces
        if (!auth()->user()->can('update roles')) {
            return redirect()->route('admin.roles.index')
                ->with('error', 'You do not have permission to update roles.');
        }

        // Prevent non-admin from updating admin role
        if ($role->name === 'admin' && !auth()->user()->hasRole('admin')) {
            return redirect()->route('admin.roles.index')
                ->with('error', 'You cannot modify the Admin role.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $role->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        // Sync permissions
        if ($request->has('permissions')) {
            $role->permissions()->sync($request->permissions);
        } else {
            $role->permissions()->sync([]);
        }

        return redirect()->route('admin.roles.index')
            ->with('success', 'Role updated successfully.');
    }

    /**
     * Remove the specified role from storage.
     */
    public function destroy(Role $role)
    {
        // Guard by permission via Gate (admin bypass respected); middleware already enforces
        if (!auth()->user()->can('delete roles')) {
            return redirect()->route('admin.roles.index')
                ->with('error', 'You do not have permission to delete roles.');
        }

        // Prevent deleting admin role (by anyone)
        if ($role->name === 'admin' || $role->name === 'super-admin') {
            return redirect()->route('admin.roles.index')
                ->with('error', 'Cannot delete system role.');
        }

        // Prevent non-admin from deleting any role (additional check)
        if ($role->name === 'admin' && !auth()->user()->hasRole('admin')) {
            return redirect()->route('admin.roles.index')
                ->with('error', 'You cannot delete the Admin role.');
        }

        // Detach role dari semua users
        $role->users()->detach();
        
        // Detach semua permissions
        $role->permissions()->detach();
        
        $role->delete();

        return redirect()->route('admin.roles.index')
            ->with('success', 'Role deleted successfully.');
    }
}
