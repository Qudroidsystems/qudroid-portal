<?php

namespace App\Http\Controllers;

use App\Exports\StaffUserBatchTemplateExport;
use App\Imports\StaffUsersImport;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

/**
 * Accounts for everyone who uses Qlouds Workspace: staff, students, clients and
 * partners. Profile details are edited on the profile page (ProfileController).
 */
class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:View user|Create user|Update user|Delete user', ['only' => ['index', 'show', 'allUsers', 'paginate', 'roles']]);
        $this->middleware('permission:Create user', ['only' => ['create', 'store', 'generateStaffTemplate', 'importStaffUsers']]);
        $this->middleware('permission:Update user', ['only' => ['edit', 'update', 'toggleStatus']]);
        $this->middleware('permission:Delete user', ['only' => ['destroy']]);
    }

    public function index(Request $request): View
    {
        $pagetitle = 'User Management';
        $data = User::with('roles')->latest()->get();
        $roles = Role::pluck('name', 'name')->toArray();
        $role_permissions = Role::all();

        $role_counts = [];
        foreach ($roles as $role) {
            $role_counts[$role] = User::role($role)->count();
        }
        $role_counts['No Role'] = User::doesntHave('roles')->count();

        $userTypes = config('qudroid.user_types');

        return view('users.index', compact('data', 'roles', 'role_permissions', 'pagetitle', 'role_counts', 'userTypes'));
    }

    public function create()
    {
        return redirect()->route('users.index');
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name'         => 'required|string|max:255',
                'email'        => 'required|email|max:255|unique:users,email',
                'password'     => 'required|min:8|confirmed',
                'roles'        => 'required|array',
                'roles.*'      => 'exists:roles,name',
                'user_type'    => ['nullable', Rule::in(array_keys(config('qudroid.user_types')))],
                'phone_number' => 'nullable|string|max:30',
                'job_title'    => 'nullable|string|max:120',
                'must_change_password' => 'nullable|boolean',
            ]);

            $plainPassword = $request->input('password');

            $user = User::create([
                'name'         => $request->input('name'),
                'email'        => strtolower(trim($request->input('email'))),
                'user_type'    => $request->input('user_type') ?: User::TYPE_STAFF,
                'phone_number' => $request->input('phone_number'),
                'job_title'    => $request->input('job_title'),
                'password'     => Hash::make($plainPassword),
            ]);
            // New accounts choose their own password at first sign-in unless the admin says otherwise.
            $user->forceFill(['must_change_password' => $request->boolean('must_change_password', true)])->save();
            $user->syncRoles($this->assignableRoles($request->input('roles')));

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'user'    => $this->present($user) + ['password' => $plainPassword],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error("Create user error: {$e->getMessage()}");
            return response()->json(['success' => false, 'message' => 'Failed to create user'], 500);
        }
    }

    public function show($id): View
    {
        $pagetitle = 'User Overview';
        $user = User::with('roles', 'permissions')->findOrFail($id);

        $recentActivity = \Illuminate\Support\Facades\Schema::hasTable('activity_logs')
            ? \Illuminate\Support\Facades\DB::table('activity_logs')->where('user_id', $user->id)->orderByDesc('id')->limit(8)->get()
            : collect();

        return view('users.overview', compact('user', 'pagetitle', 'recentActivity'));
    }

    public function edit($id)
    {
        return redirect()->route('profile.settings', $id);
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'name'         => 'required|string|max:255',
                'email'        => 'required|email|max:255|unique:users,email,' . $id,
                'password'     => 'nullable|min:8|confirmed',
                'roles'        => 'required|array',
                'roles.*'      => 'exists:roles,name',
                'user_type'    => ['nullable', Rule::in(array_keys(config('qudroid.user_types')))],
                'phone_number' => 'nullable|string|max:30',
                'job_title'    => 'nullable|string|max:120',
            ]);

            $user = User::findOrFail($id);
            $roles = $this->assignableRoles($request->input('roles'), $user);

            if ($this->wouldRemoveLastSuperAdmin($user, $roles)) {
                return response()->json(['success' => false, 'message' => 'There must always be at least one Super Administrator.'], 422);
            }

            $input = Arr::only($request->all(), ['name', 'email', 'user_type', 'phone_number', 'job_title']);
            $input = array_filter($input, fn ($v) => $v !== null);
            $plainPassword = $request->filled('password') ? $request->input('password') : null;
            if ($plainPassword) {
                $input['password'] = Hash::make($plainPassword);
            }

            $user->update($input);
            $user->syncRoles($roles);

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'user'    => $this->present($user->fresh('roles')) + ['password' => $plainPassword],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error("Update user error: {$e->getMessage()}");
            return response()->json(['success' => false, 'message' => 'Failed to update user'], 500);
        }
    }

    /** Disable or re-enable sign-in for an account. */
    public function toggleStatus($id): JsonResponse
    {
        $user = User::findOrFail($id);
        if ($user->id === auth()->id()) {
            return response()->json(['success' => false, 'message' => 'You cannot disable your own account.'], 422);
        }
        if (!$user->is_disabled && $user->isSuperAdmin() && User::role(config('qudroid.super_admin_role'))->where('is_disabled', false)->count() <= 1) {
            return response()->json(['success' => false, 'message' => 'There must always be at least one active Super Administrator.'], 422);
        }

        $user->forceFill(['is_disabled' => !$user->is_disabled])->save();
        if ($user->is_disabled) {
            $user->tokens()->delete(); // sign out of the mobile app at once
        }

        return response()->json([
            'success'     => true,
            'is_disabled' => $user->is_disabled,
            'message'     => $user->is_disabled ? 'Account disabled.' : 'Account enabled.',
        ]);
    }

    public function destroy($id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);

            if ($user->id === auth()->id()) {
                return response()->json(['success' => false, 'message' => 'You cannot delete your own account.'], 422);
            }
            if ($this->wouldRemoveLastSuperAdmin($user, [])) {
                return response()->json(['success' => false, 'message' => 'There must always be at least one Super Administrator.'], 422);
            }

            $user->roles()->detach();
            $user->permissions()->detach();
            $user->tokens()->delete();
            $user->delete();

            return response()->json(['success' => true, 'message' => 'User deleted successfully']);
        } catch (\Throwable $e) {
            Log::error("Delete user error: {$e->getMessage()}");
            return response()->json(['success' => false, 'message' => 'Failed to delete user'], 500);
        }
    }

    public function roles(): JsonResponse
    {
        return response()->json(['roles' => Role::pluck('name')->all()]);
    }

    public function allUsers(Request $request): JsonResponse
    {
        return response()->json(['users' => User::with('roles')->get()]);
    }

    public function paginate(Request $request): JsonResponse
    {
        return response()->json(User::with('roles')->paginate(15));
    }

    /** Blank Excel template for creating staff accounts in bulk. */
    public function generateStaffTemplate(Request $request)
    {
        $request->validate(['rows' => 'nullable|integer|min:1|max:200']);

        $rows = (int) $request->input('rows', 30);
        $filename = 'Staff_Users_Batch_Template_' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(new StaffUserBatchTemplateExport($rows), $filename);
    }

    /** Create staff accounts from the filled template. */
    public function importStaffUsers(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'filesheet' => 'required|mimes:xlsx,xls,csv|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $import = new StaffUsersImport();
            Excel::import($import, $request->file('filesheet'));

            $created  = $import->getCreated();
            $skipped  = $import->getSkipped();
            $failures = $import->failures();

            $message = count($created) . ' staff user(s) created successfully.';
            if (count($skipped) > 0) {
                $message .= ' ' . count($skipped) . ' row(s) skipped.';
            }

            return response()->json([
                'success'       => true,
                'message'       => $message,
                'created'       => $created,
                'skipped'       => $skipped,
                'failures'      => collect($failures)->map(fn ($f) => [
                    'row'       => $f->row(),
                    'attribute' => $f->attribute(),
                    'errors'    => $f->errors(),
                ])->values(),
                'created_count' => count($created),
                'skipped_count' => count($skipped),
            ]);
        } catch (\Throwable $e) {
            Log::error('Staff user import failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Import failed: ' . $e->getMessage()], 500);
        }
    }

    // ── helpers ────────────────────────────────────────────────────────

    /**
     * Only a Super Administrator may hand out the Super Administrator role.
     * Anyone else keeps the target's existing super-admin status unchanged.
     */
    protected function assignableRoles(array $roles, ?User $target = null): array
    {
        $super = config('qudroid.super_admin_role');
        if (auth()->user()?->isSuperAdmin()) {
            return array_values($roles);
        }

        $roles = array_values(array_diff($roles, [$super]));
        if ($target && $target->hasRole($super)) {
            $roles[] = $super;
        }
        return $roles;
    }

    protected function wouldRemoveLastSuperAdmin(User $user, array $newRoles): bool
    {
        $super = config('qudroid.super_admin_role');
        if (!$user->hasRole($super) || in_array($super, $newRoles, true)) {
            return false;
        }
        return User::role($super)->count() <= 1;
    }

    protected function present(User $user): array
    {
        return [
            'id'           => $user->id,
            'name'         => $user->name,
            'email'        => $user->email,
            'user_type'    => $user->user_type,
            'roles'        => $user->roles->pluck('name')->toArray(),
            'phone_number' => $user->phone_number,
        ];
    }
}
