<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CampaignWorkflow;
use App\Models\Integration;
use App\Models\IvrFlow;
use App\Models\IvrSession;
use App\Services\AI\IVRFlowEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class IvrFlowController extends Controller
{
    public function __construct(
        private readonly IVRFlowEngine $flowEngine
    ) {}

    public function index(): JsonResponse
    {
        $flows = IvrFlow::orderByDesc('is_active')->latest()->get();
        $recentSessions = IvrSession::with('call.contact:id,first_name,last_name,phone_primary')
            ->latest('started_at')
            ->limit(15)
            ->get();

        $voipIntegration = Integration::where('type', 'voip')->first();
        $receptionMode = $voipIntegration?->config['reception_mode'] ?? 'hybrid';

        return response()->json([
            'flows' => $flows,
            'recent_sessions' => $recentSessions,
            'reception_mode' => $receptionMode,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'nodes' => ['required', 'array'],
            'edges' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        if (! empty($validated['is_active'])) {
            IvrFlow::query()->update(['is_active' => false]);
        }

        $flow = IvrFlow::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'nodes' => $validated['nodes'],
            'edges' => $validated['edges'],
            'is_active' => $validated['is_active'] ?? true,
            'published_at' => ! empty($validated['is_active']) ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'جریان منشی هوشمند با موفقیت ذخیره شد.',
            'flow' => $flow,
        ], 201);
    }

    public function show(IvrFlow $flow): JsonResponse
    {
        return response()->json([
            'flow' => $flow,
        ]);
    }

    public function update(Request $request, IvrFlow $flow): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'nodes' => ['sometimes', 'array'],
            'edges' => ['sometimes', 'array'],
            'is_active' => ['boolean'],
        ]);

        if (! empty($validated['is_active'])) {
            IvrFlow::where('id', '!=', $flow->id)->update(['is_active' => false]);
            $validated['published_at'] = now();
        }

        $flow->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'جریان منشی هوشمند به‌روزرسانی شد.',
            'flow' => $flow,
        ]);
    }

    public function destroy(IvrFlow $flow): JsonResponse
    {
        $flow->delete();

        return response()->json([
            'success' => true,
            'message' => 'جریان منشی هوشمند حذف شد.',
        ]);
    }

    public function publish(IvrFlow $flow): JsonResponse
    {
        IvrFlow::where('id', '!=', $flow->id)->update(['is_active' => false]);

        $flow->update([
            'is_active' => true,
            'published_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'جریان منشی هوشمند منتشر و به عنوان جریان پیش‌فرض فعال شد.',
            'flow' => $flow,
        ]);
    }

    public function updateReceptionMode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reception_mode' => ['required', 'string', 'in:human_only,ai_only,hybrid'],
        ]);

        $integration = Integration::where('type', 'voip')->first() ?? new Integration([
            'name' => 'Asterisk PBX مرکزی',
            'type' => 'voip',
            'adapter_class' => 'asterisk',
            'status' => 'active',
        ]);

        $config = $integration->config ?? ['host' => '127.0.0.1', 'port' => 8088];
        $config['reception_mode'] = $validated['reception_mode'];
        $integration->config = $config;
        $integration->save();

        return response()->json([
            'success' => true,
            'message' => 'حالت پاسخگویی سامانه با موفقیت تغییر یافت.',
            'reception_mode' => $validated['reception_mode'],
        ]);
    }

    /**
     * Simulate or process a live Persian AI Receptionist interaction step.
     */
    public function simulate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'caller_number' => ['nullable', 'string', 'max:30'],
            'utterance' => ['nullable', 'string'],
            'dtmf' => ['nullable', 'string', 'max:5'],
            'flow_id' => ['nullable', 'exists:ivr_flows,id'],
        ]);

        $result = $this->flowEngine->processInteraction($validated);

        return response()->json([
            'success' => true,
            'simulation' => $result,
        ]);
    }

    // =========================================================================
    // Campaign Workflow Visual Builder Endpoints
    // =========================================================================

    public function campaignWorkflows(): JsonResponse
    {
        return response()->json([
            'workflows' => CampaignWorkflow::latest()->get(),
        ]);
    }

    public function storeCampaignWorkflow(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'nodes' => ['required', 'array'],
            'edges' => ['nullable', 'array'],
            'is_template' => ['boolean'],
        ]);

        $validated['edges'] = $validated['edges'] ?? [];
        $workflow = CampaignWorkflow::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'گردش کار بصری کمپین ذخیره شد.',
            'workflow' => $workflow,
        ], 201);
    }

    public function updateCampaignWorkflow(Request $request, CampaignWorkflow $workflow): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'nodes' => ['sometimes', 'array'],
            'edges' => ['sometimes', 'array'],
            'is_template' => ['boolean'],
        ]);

        $workflow->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'گردش کار کمپین به‌روزرسانی شد.',
            'workflow' => $workflow,
        ]);
    }
}
