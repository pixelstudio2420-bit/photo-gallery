<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminManagementController extends Controller
{
    /**
     * List all admin accounts.
     */
    public function index(Request $request)
    {
        $admins = Admin::query()
            ->when($request->q, fn($q, $s) => $q->where(fn($q2) => $q2->where('first_name', 'ilike', "%{$s}%")->orWhere('last_name', 'ilike', "%{$s}%")->orWhere('email', 'ilike', "%{$s}%")))
            ->when($request->status, fn($q, $s) => $q->where('is_active', $s === 'active' ? 1 : 0))
            ->orderByRaw("CASE role WHEN 'superadmin' THEN 1 WHEN 'admin' THEN 2 WHEN 'editor' THEN 3 ELSE 4 END")
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('admin.admins.index', compact('admins'));
    }

    /**
     * Show create form.
     */
    public function create()
    {
        return view('admin.admins.create');
    }

    /**
     * Store new admin.
     */
    public function store(Request $request)
    {
        $request->validate([
            'email'      => 'required|email|unique:auth_admins,email',
            'password'   => 'required|min:8|confirmed',
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'role'       => ['required', Rule::in([Admin::ROLE_ADMIN, Admin::ROLE_EDITOR])],
            'permissions' => 'nullable|array',
        ]);

        // Only superadmin can create superadmin (blocked at route level too)
        if ($request->role === Admin::ROLE_SUPERADMIN) {
            return back()->with('error', 'ไม่สามารถสร้างบัญชี Super Admin เพิ่มได้');
        }

        $permissions = $request->permissions ?? Admin::defaultPermissions($request->role);

        $admin = Admin::create([
            'email'         => $request->email,
            'password_hash' => Hash::make($request->password),
            'first_name'    => $request->first_name,
            'last_name'     => $request->last_name,
            'role'          => $request->role,
            'permissions'   => $permissions,
            'is_active'     => true,
        ]);

        ActivityLogger::admin(
            action: 'admin.created',
            target: $admin,
            description: "สร้างบัญชีแอดมินใหม่ {$admin->email} (role: {$admin->role})",
            oldValues: null,
            newValues: [
                'admin_id'    => (int) $admin->id,
                'email'       => $admin->email,
                'full_name'   => $admin->full_name,
                'role'        => $admin->role,
                'permissions' => $permissions,
                'is_active'   => true,
            ],
        );

        return redirect()->route('admin.admins.index')
            ->with('success', "สร้างบัญชี {$admin->full_name} สำเร็จ");
    }

    /**
     * Show edit form.
     */
    public function edit(Admin $admin)
    {
        $currentAdmin = Auth::guard('admin')->user();

        // Cannot edit a superadmin unless you are that superadmin
        if ($admin->isSuperAdmin() && $admin->id !== $currentAdmin->id) {
            return redirect()->route('admin.admins.index')
                ->with('error', 'ไม่สามารถแก้ไขบัญชี Super Admin คนอื่นได้');
        }

        return view('admin.admins.edit', compact('admin'));
    }

    /**
     * Update admin account.
     */
    public function update(Request $request, Admin $admin)
    {
        $currentAdmin = Auth::guard('admin')->user();

        // Protect superadmin from being edited by others
        if ($admin->isSuperAdmin() && $admin->id !== $currentAdmin->id) {
            return back()->with('error', 'ไม่สามารถแก้ไขบัญชี Super Admin คนอื่นได้');
        }

        $rules = [
            'email'      => ['required', 'email', Rule::unique('auth_admins')->ignore($admin->id)],
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'password'   => 'nullable|min:8|confirmed',
        ];

        // Can't change own role if superadmin
        if (!$admin->isSuperAdmin()) {
            $rules['role'] = ['required', Rule::in([Admin::ROLE_ADMIN, Admin::ROLE_EDITOR])];
            $rules['permissions'] = 'nullable|array';
        }

        $request->validate($rules);

        // Snapshot for audit
        $oldSnapshot = [
            'email'       => $admin->email,
            'first_name'  => $admin->first_name,
            'last_name'   => $admin->last_name,
            'role'        => $admin->role,
            'permissions' => is_array($admin->permissions) ? $admin->permissions : [],
        ];

        $data = [
            'email'      => $request->email,
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
        ];

        $passwordChanged = false;
        if ($request->filled('password')) {
            $data['password_hash'] = Hash::make($request->password);
            $passwordChanged = true;
        }

        if (!$admin->isSuperAdmin()) {
            $data['role'] = $request->role;
            $data['permissions'] = $request->permissions ?? [];
        }

        $admin->update($data);

        $newSnapshot = [
            'email'       => $admin->email,
            'first_name'  => $admin->first_name,
            'last_name'   => $admin->last_name,
            'role'        => $admin->role,
            'permissions' => is_array($admin->permissions) ? $admin->permissions : [],
        ];

        ActivityLogger::admin(
            action: 'admin.updated',
            target: $admin,
            description: "แก้ไขข้อมูลแอดมิน {$admin->email}" . ($passwordChanged ? ' (รวมรหัสผ่าน)' : ''),
            oldValues: $oldSnapshot,
            newValues: array_merge($newSnapshot, ['password_changed' => $passwordChanged]),
        );

        return redirect()->route('admin.admins.index')
            ->with('success', "อัปเดต {$admin->full_name} สำเร็จ");
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleStatus(Admin $admin)
    {
        $currentAdmin = Auth::guard('admin')->user();

        // Cannot deactivate yourself
        if ($admin->id === $currentAdmin->id) {
            return back()->with('error', 'ไม่สามารถระงับบัญชีตัวเองได้');
        }

        // Cannot deactivate superadmin
        if ($admin->isSuperAdmin()) {
            return back()->with('error', 'ไม่สามารถระงับบัญชี Super Admin ได้');
        }

        $oldActive = (bool) $admin->is_active;
        $admin->update(['is_active' => !$oldActive]);

        ActivityLogger::admin(
            action: 'admin.status_toggled',
            target: $admin,
            description: ($admin->is_active ? 'เปิดใช้งาน' : 'ระงับ') . "บัญชีแอดมิน {$admin->email}",
            oldValues: ['is_active' => $oldActive],
            newValues: ['is_active' => (bool) $admin->is_active],
        );

        $status = $admin->is_active ? 'เปิดใช้งาน' : 'ระงับ';

        return back()->with('success', "{$status}บัญชี {$admin->full_name} สำเร็จ");
    }

    /**
     * Delete admin account.
     */
    public function destroy(Admin $admin)
    {
        $currentAdmin = Auth::guard('admin')->user();

        if ($admin->id === $currentAdmin->id) {
            return back()->with('error', 'ไม่สามารถลบบัญชีตัวเองได้');
        }

        if ($admin->isSuperAdmin()) {
            return back()->with('error', 'ไม่สามารถลบบัญชี Super Admin ได้');
        }

        $name = $admin->full_name;
        $snapshot = [
            'id'         => $admin->id,
            'email'      => $admin->email,
            'full_name'  => $name,
            'role'       => $admin->role,
            'is_active'  => (bool) $admin->is_active,
        ];
        $admin->delete();

        ActivityLogger::admin(
            action: 'admin.deleted',
            target: ['Admin', (int) $snapshot['id']],
            description: "ลบบัญชีแอดมิน {$snapshot['email']}",
            oldValues: $snapshot,
            newValues: null,
        );

        return redirect()->route('admin.admins.index')
            ->with('success', "ลบบัญชี {$name} สำเร็จ");
    }

    /**
     * Update permissions for a specific admin (AJAX).
     */
    public function updatePermissions(Request $request, Admin $admin)
    {
        if ($admin->isSuperAdmin()) {
            return response()->json(['error' => 'Cannot modify superadmin permissions'], 403);
        }

        $request->validate(['permissions' => 'required|array']);

        $validKeys = Admin::allPermissionKeys();
        $permissions = array_intersect($request->permissions, $validKeys);

        $oldPermissions = is_array($admin->permissions) ? $admin->permissions : [];
        $newPermissions = array_values($permissions);

        $admin->update(['permissions' => $newPermissions]);

        ActivityLogger::admin(
            action: 'admin.permissions_updated',
            target: $admin,
            description: "ปรับสิทธิ์ของแอดมิน {$admin->email}",
            oldValues: ['permissions' => $oldPermissions],
            newValues: [
                'permissions' => $newPermissions,
                'added'       => array_values(array_diff($newPermissions, $oldPermissions)),
                'removed'     => array_values(array_diff($oldPermissions, $newPermissions)),
            ],
        );

        return response()->json([
            'success' => true,
            'message' => "อัปเดตสิทธิ์ของ {$admin->full_name} สำเร็จ",
        ]);
    }
}
