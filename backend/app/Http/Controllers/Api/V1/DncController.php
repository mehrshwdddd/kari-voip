<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\DoNotCall;
use App\Services\Campaign\DNCChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DncController extends Controller
{
    public function __construct(
        private readonly DNCChecker $dncChecker
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Campaign::class);

        $query = DoNotCall::with('creator:id,name');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('phone_number', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        $dncList = $query->orderByDesc('added_at')->paginate($request->integer('per_page', 30));

        return response()->json($dncList);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Campaign::class);

        $validated = $request->validate([
            'phone_number' => ['required', 'string', 'max:30'],
            'reason' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'in:manual,dtmf_opt_out,complaint,import'],
        ]);

        $dnc = $this->dncChecker->optOut(
            $validated['phone_number'],
            $validated['source'] ?? 'manual',
            $validated['reason'] ?? 'ثبت دستی در لیست عدم تماس (DNC)',
            $request->user()->id
        );

        return response()->json([
            'success' => true,
            'message' => 'شماره با موفقیت به لیست سیاه عدم تماس (DNC) اضافه شد.',
            'dnc' => $dnc->load('creator:id,name'),
        ], 201);
    }

    public function destroy(DoNotCall $dnc): JsonResponse
    {
        $this->authorize('create', Campaign::class);

        $dnc->delete();

        return response()->json([
            'success' => true,
            'message' => 'شماره از لیست سیاه عدم تماس (DNC) خارج شد.',
        ]);
    }
}
