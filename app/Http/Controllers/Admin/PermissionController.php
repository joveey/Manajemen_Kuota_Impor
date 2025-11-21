<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; 
use Illuminate\Support\Facades\Validator;

class PermissionController extends Controller
{
    public function __construct()
    {
        // Everyone with read permission can see the list/detail
        $this->middleware('permission:read permissions')->only(['index', 'show']);
        // CRUD controlled by specific permissions
        $this->middleware('permission:create permissions')->only(['create', 'store']);
        $this->middleware('permission:update permissions')->only(['edit', 'update']);
        $this->middleware('permission:delete permissions')->only(['destroy']);
    }
    /**
     * Display a listing of the permissions.
     */
    public function index()
    {
        $permissions = Permission::orderBy('name', 'asc')->paginate(10);
        $stats = [
            'total' => Permission::count(),
            'create' => Permission::where('name', 'create')->count(),
            'read_all' => Permission::where('name', 'read')->count(),
            'read_limited' => Permission::where('name', 'read limited')->count(),
        ];
        
        return view('admin.permissions.index', compact('permissions', 'stats'));
    }

    /**
     * Show the form for creating a new permission.
     */
    public function create()
    {
        /** @var \App\Models\User $currentUser */
        $currentUser = Auth::user();

        // Guard by permission (middleware already enforces)
        if (!$currentUser->can('create permissions')) {
            return redirect()->route('admin.permissions.index')
                ->with('error', 'You do not have permission to create permissions.');
        }

        return view('admin.permissions.create');
    }

    /**
     * Store a newly created permission in storage.
     */
    public function store(Request $request)
    {
        /** @var \App\Models\User $currentUser */
        $currentUser = Auth::user();

        // Guard by permission (middleware already enforces)
        if (!$currentUser->can('create permissions')) {
            return redirect()->route('admin.permissions.index')
                ->with('error', 'You do not have permission to create permissions.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:permissions,name',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Validasi: hanya izinkan 'create', 'read', atau 'read limited'
        $allowedNames = ['create', 'read', 'read limited'];
        $name = strtolower(trim($request->name));
        if (!in_array($name, $allowedNames, true)) {
            return redirect()->back()
                ->withErrors(['name' => "Permission name must be exactly 'create', 'read', or 'read limited'"])
                ->withInput();
        }

        Permission::create([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return redirect()->route('admin.permissions.index')
            ->with('success', 'Permission created successfully.');
    }

    /**
     * Display the specified permission.
     */
    public function show(Permission $permission)
    {
        $roles = $permission->roles()->paginate(10);
        
        return view('admin.permissions.show', compact('permission', 'roles'));
    }

    /**
     * Show the form for editing the specified permission.
     */
    public function edit(Permission $permission)
    {
        /** @var \App\Models\User $currentUser */
        $currentUser = Auth::user();

        // Guard by permission (middleware already enforces)
        if (!$currentUser->can('update permissions')) {
            return redirect()->route('admin.permissions.index')
                ->with('error', 'You do not have permission to edit permissions.');
        }

        return view('admin.permissions.edit', compact('permission'));
    }

    /**
     * Update the specified permission in storage.
     */
    public function update(Request $request, Permission $permission)
    {
        /** @var \App\Models\User $currentUser */
        $currentUser = Auth::user();

        // Guard by permission (middleware already enforces)
        if (!$currentUser->can('update permissions')) {
            return redirect()->route('admin.permissions.index')
                ->with('error', 'You do not have permission to update permissions.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:permissions,name,' . $permission->id,
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Validasi: hanya izinkan 'create', 'read', atau 'read limited'
        $allowedNames = ['create', 'read', 'read limited'];
        $name = strtolower(trim($request->name));
        if (!in_array($name, $allowedNames, true)) {
            return redirect()->back()
                ->withErrors(['name' => "Permission name must be exactly 'create', 'read', or 'read limited'"])
                ->withInput();
        }

        $permission->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return redirect()->route('admin.permissions.index')
            ->with('success', 'Permission updated successfully.');
    }

    /**
     * Remove the specified permission from storage.
     */
    public function destroy(Permission $permission)
    {
        /** @var \App\Models\User $currentUser */
        $currentUser = Auth::user();

        // Guard by permission (middleware already enforces)
        if (!$currentUser->can('delete permissions')) {
            return redirect()->route('admin.permissions.index')
                ->with('error', 'You do not have permission to delete permissions.');
        }

        // Detach permission from all roles before deleting
        $permission->roles()->detach();
        
        $permission->delete();

        return redirect()->route('admin.permissions.index')
            ->with('success', 'Permission deleted successfully.');
    }
}
