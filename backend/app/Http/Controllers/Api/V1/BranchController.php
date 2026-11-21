<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BranchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Branch::with(['manager:id,name,email,phone'])
            ->withCount(['users', 'contacts']);

        // Branch-scoped visibility if limited
        if ($user->branch_id && ! $user->hasRole('owner')) {
            $query->where('id', $user->branch_id);
        }

        $branches = $query->orderBy('name')->get();

        return response()->json([
            'branches' => $branches,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Branch::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:branches,code'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'is_active' => ['boolean'],
            'working_hours' => ['nullable', 'array'],
        ]);

        $branch = Branch::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'شعبه جدید با موفقیت ایجاد شد.',
            'branch' => $branch->load('manager:id,name'),
        ], 201);
    }

    public function show(Branch $branch): JsonResponse
    {
        $this->authorize('view', $branch);

        return response()->json([
            'branch' => $branch->load(['manager:id,name,email,phone'])->loadCount(['users', 'contacts']),
        ]);
    }

    public function update(Request $request, Branch $branch): JsonResponse
    {
        $this->authorize('update', $branch);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', "unique:branches,code,{$branch->id}"],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'is_active' => ['boolean'],
            'working_hours' => ['nullable', 'array'],
        ]);

        $branch->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'شعبه با موفقیت ویرایش شد.',
            'branch' => $branch->load('manager:id,name'),
        ]);
    }

    public function destroy(Branch $branch): JsonResponse
    {
        $this->authorize('delete', $branch);

        $branch->delete();

        return response()->json([
            'success' => true,
            'message' => 'شعبه با موفقیت حذف گردید.',
        ]);
    }
}
