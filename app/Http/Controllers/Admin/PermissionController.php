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
        
        return view('admin.permissions.index', compact('permissions'));
    }

    /**
     * Show the form for creating a new permission.
     */
    public function create()
    {
        // Guard by permission (middleware already enforces)
        if (!Auth::user()->hasPermission('create permissions')) {
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
        // Guard by permission (middleware already enforces)
        if (!Auth::user()->hasPermission('create permissions')) {
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

        // Validasi bahwa permission harus dimulai dengan create, read, update, atau delete
        $validPrefixes = ['create', 'read', 'update', 'delete'];
        $hasValidPrefix = false;
        
        foreach ($validPrefixes as $prefix) {
            if (str_starts_with(strtolower($request->name), $prefix)) {
                $hasValidPrefix = true;
                break;
            }
        }

        if (!$hasValidPrefix) {
            return redirect()->back()
                ->withErrors(['name' => 'Permission name must start with create, read, update, or delete'])
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
        // Guard by permission (middleware already enforces)
        if (!Auth::user()->hasPermission('update permissions')) {
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
        // Guard by permission (middleware already enforces)
        if (!Auth::user()->hasPermission('update permissions')) {
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

        // Validasi bahwa permission harus dimulai dengan create, read, update, atau delete
        $validPrefixes = ['create', 'read', 'update', 'delete'];
        $hasValidPrefix = false;
        
        foreach ($validPrefixes as $prefix) {
            if (str_starts_with(strtolower($request->name), $prefix)) {
                $hasValidPrefix = true;
                break;
            }
        }

        if (!$hasValidPrefix) {
            return redirect()->back()
                ->withErrors(['name' => 'Permission name must start with create, read, update, or delete'])
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
        // Guard by permission (middleware already enforces)
        if (!Auth::user()->hasPermission('delete permissions')) {
            return redirect()->route('admin.permissions.index')
                ->with('error', 'You do not have permission to delete permissions.');
        }

        // Detach permission dari semua roles sebelum dihapus
        $permission->roles()->detach();
        
        $permission->delete();

        return redirect()->route('admin.permissions.index')
            ->with('success', 'Permission deleted successfully.');
    }
}
