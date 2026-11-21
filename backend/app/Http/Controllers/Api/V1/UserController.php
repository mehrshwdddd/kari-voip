<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

final class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $currentUser = $request->user();
        $query = User::with(['branch', 'roles']);

        if ($currentUser->branch_id && ! $currentUser->hasRole('owner')) {
            $query->where('branch_id', $currentUser->branch_id);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        $users = $query->latest()->paginate($request->integer('per_page', 20));

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'regex:/^09[0-9]{9}$/', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'role' => ['required', 'string', 'exists:roles,name'],
            'status' => ['nullable', 'string', 'in:active,inactive,suspended'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'branch_id' => $validated['branch_id'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        $user->assignRole($validated['role']);

        return response()->json([
            'success' => true,
            'message' => 'کاربر جدید با موفقیت ایجاد شد.',
            'user' => $user->load(['branch', 'roles']),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return response()->json([
            'user' => $user->load(['branch', 'roles', 'permissions']),
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'regex:/^09[0-9]{9}$/', Rule::unique('users')->ignore($user->id)],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'role' => ['sometimes', 'string', 'exists:roles,name'],
            'status' => ['sometimes', 'string', 'in:active,inactive,suspended'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $data = [
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'phone' => $validated['phone'] ?? null,
            'branch_id' => $validated['branch_id'] ?? null,
        ];

        if (! empty($validated['status'])) {
            $data['status'] = $validated['status'];
        }

        if (! empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);

        if (! empty($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'اطلاعات کاربر با موفقیت به‌روزرسانی شد.',
            'user' => $user->load(['branch', 'roles']),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'کاربر با موفقیت حذف شد.',
        ]);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'رمز عبور با موفقیت بازنشانی شد.',
        ]);
    }
}
