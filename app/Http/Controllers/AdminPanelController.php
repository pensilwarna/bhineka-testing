<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

class AdminPanelController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    public function rolesIndex()
    {
        $roles = Role::all();
        return view('admin.roles.index', compact('roles'));
    }

    public function rolesCreate()
    {
        return view('admin.roles.create');
    }

    public function rolesStore(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:roles,name',
        ]);

        Role::create(['name' => $request->name]);

        return redirect()->route('admin.roles.index')->with('success', 'Role berhasil dibuat!');
    }

    public function rolesShow(Role $role)
    {
        $permissions = Permission::all();
        $rolePermissions = $role->permissions->pluck('name')->toArray();
        return view('admin.roles.show', compact('role', 'permissions', 'rolePermissions'));
    }

    public function rolesEdit(Role $role)
    {
        return view('admin.roles.edit', compact('role'));
    }

    public function rolesUpdate(Request $request, Role $role)
    {
        $request->validate([
            'name' => 'required|unique:roles,name,' . $role->id,
        ]);

        $role->update(['name' => $request->name]);

        return redirect()->route('admin.roles.index')->with('success', 'Role berhasil diperbarui!');
    }

    public function rolesDestroy(Role $role)
    {
        // Pastikan tidak menghapus role 'admin' atau role penting lainnya jika ada
        if ($role->name === 'admin') {
            return redirect()->route('admin.roles.index')->with('error', 'Role "admin" tidak dapat dihapus.');
        }

        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', 'Role berhasil dihapus!');
    }

    public function permissionsIndex()
    {
        $permissions = Permission::all();
        return view('admin.permissions.index', compact('permissions'));
    }

    public function permissionsCreate()
    {
        return view('admin.permissions.create');
    }

    public function permissionsStore(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:permissions,name',
        ]);

        Permission::create(['name' => $request->name]);

        return redirect()->route('admin.permissions.index')->with('success', 'Permission berhasil dibuat!');
    }

    public function permissionsShow(Permission $permission)
    {
        return view('admin.permissions.show', compact('permission'));
    }

    public function permissionsEdit(Permission $permission)
    {
        return view('admin.permissions.edit', compact('permission'));
    }

    public function permissionsUpdate(Request $request, Permission $permission)
    {
        $request->validate([
            'name' => 'required|unique:permissions,name,' . $permission->id,
        ]);

        $permission->update(['name' => $request->name]);

        return redirect()->route('admin.permissions.index')->with('success', 'Permission berhasil diperbarui!');
    }

    public function permissionsDestroy(Permission $permission)
    {
        // Anda mungkin perlu logika tambahan di sini untuk memastikan permission tidak digunakan oleh role mana pun
        $permission->delete();

        return redirect()->route('admin.permissions.index')->with('success', 'Permission berhasil dihapus!');
    }

    public function syncPermissions(Request $request, Role $role)
    {
        $permissions = $request->input('permissions', []);
        $role->syncPermissions($permissions); // Method dari Spatie Permission

        return redirect()->route('admin.roles.show', $role->id)->with('success', 'Permissions berhasil disinkronkan!');
    }


    /**
     * Menampilkan daftar users dan role mereka
     */
    public function usersIndex()
    {
        $users = \App\Models\User::with('roles')->get(); // Pastikan model User diimport
        return view('admin.users.index', compact('users'));
    }

    /**
     * Menampilkan form untuk mengelola role user
     */
    public function usersEditRoles(\App\Models\User $user)
    {
        $roles = Role::all();
        $userRoles = $user->roles->pluck('name')->toArray();
        return view('admin.users.edit_roles', compact('user', 'roles', 'userRoles'));
    }

    /**
     * Mengupdate role user
     */
    public function usersUpdateRoles(Request $request, \App\Models\User $user)
    {
        $roles = $request->input('roles', []);
        $user->syncRoles($roles); // Method dari Spatie Permission

        return redirect()->route('admin.users.index')->with('success', 'Role user berhasil diperbarui!');
    }
}
