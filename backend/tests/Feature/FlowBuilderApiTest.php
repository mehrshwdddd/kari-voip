<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlowBuilderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_visual_ivr_flow_and_campaign_workflow_crud_and_publishing(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $owner->assignRole('owner');

        // 1. Create visual IVR Flow
        $flowRes = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/ivr/flows', [
            'name' => 'جریان پذیرش کلینیک دندانپزشکی',
            'description' => 'نوبت‌دهی خودکار دندانپزشکی',
            'nodes' => [
                ['id' => 'n1', 'type' => 'greeting', 'position' => ['x' => 100, 'y' => 50], 'data' => ['title' => 'خوش‌آمدگویی']],
                ['id' => 'n2', 'type' => 'book_appointment', 'position' => ['x' => 100, 'y' => 200], 'data' => ['title' => 'رزرو وقت']],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'n1', 'target' => 'n2', 'label' => 'کلید ۱'],
            ],
            'is_active' => true,
        ]);

        $flowRes->assertStatus(201)
            ->assertJsonPath('flow.is_active', true)
            ->assertJsonCount(2, 'flow.nodes');

        $flowId = $flowRes->json('flow.id');

        // 2. Switch reception mode
        $modeRes = $this->actingAs($owner, 'sanctum')->putJson('/api/v1/ivr/reception-mode', [
            'reception_mode' => 'hybrid',
        ]);
        $modeRes->assertOk()
            ->assertJsonPath('reception_mode', 'hybrid');

        // 3. Publish flow
        $pubRes = $this->actingAs($owner, 'sanctum')->postJson("/api/v1/ivr/flows/{$flowId}/publish");
        $pubRes->assertOk()
            ->assertJsonPath('flow.is_active', true);

        // 4. Create Campaign Workflow
        $cwRes = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/campaign-workflows', [
            'name' => 'گردش کار کمپین نظرسنجی',
            'nodes' => [
                ['id' => 'c1', 'type' => 'dial', 'data' => ['title' => 'تماس خروجی']],
            ],
            'edges' => [],
            'is_template' => true,
        ]);
        $cwRes->assertStatus(201)
            ->assertJsonPath('workflow.name', 'گردش کار کمپین نظرسنجی');
    }
}
