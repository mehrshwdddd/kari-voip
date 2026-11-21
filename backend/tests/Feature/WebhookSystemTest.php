<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Webhook;
use App\Services\WebhookDispatcherService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_can_manage_webhooks_and_dispatch_signed_events(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $owner->assignRole('owner');

        // 1. Create Webhook
        $createRes = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/developer/webhooks', [
            'name' => 'سامانه حسابداری سپیدار',
            'url' => 'https://sepidar.example.com/api/webhooks/voicereach',
            'secret' => 'super_secret_signing_key_12345678',
            'events' => ['invoice.paid', 'appointment.created'],
            'is_active' => true,
        ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.name', 'سامانه حسابداری سپیدار');

        $webhookId = $createRes->json('data.id');

        // 2. Dispatch event with Mocked HTTP
        Http::fake([
            'https://sepidar.example.com/*' => Http::response(['received' => true], 200),
        ]);

        $dispatcher = app(WebhookDispatcherService::class);
        $deliveries = $dispatcher->dispatch('invoice.paid', [
            'invoice_id' => 101,
            'amount' => 5000000,
            'customer_name' => 'شرکت توسعه تجارت',
        ]);

        $this->assertCount(1, $deliveries);
        $delivery = $deliveries[0];
        $this->assertEquals(200, $delivery->response_status);
        $this->assertNotNull($delivery->delivered_at);
        $this->assertNull($delivery->failed_at);

        // Verify HTTP request was signed properly
        Http::assertSent(function ($request) {
            $hasEventHeader = $request->hasHeader('X-VoiceReach-Event', 'invoice.paid');
            $hasTimestamp = $request->hasHeader('X-VoiceReach-Timestamp');
            $hasSignature = $request->hasHeader('X-VoiceReach-Signature');

            $timestamp = $request->header('X-VoiceReach-Timestamp')[0];
            $signature = $request->header('X-VoiceReach-Signature')[0];

            $expectedSig = hash_hmac('sha256', $timestamp.'.'.$request->body(), 'super_secret_signing_key_12345678');

            return $hasEventHeader && $hasTimestamp && $hasSignature && hash_equals($expectedSig, $signature);
        });

        // 3. Test Webhook endpoint directly via Controller
        $testRes = $this->actingAs($owner, 'sanctum')->postJson("/api/v1/developer/webhooks/{$webhookId}/test");
        $testRes->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.event_type', 'ping');

        // 4. View deliveries history
        $deliveriesRes = $this->actingAs($owner, 'sanctum')->getJson("/api/v1/developer/webhooks/{$webhookId}/deliveries");
        $deliveriesRes->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.total', 2); // 1 from dispatch + 1 from test

        // 5. Delete webhook
        $deleteRes = $this->actingAs($owner, 'sanctum')->deleteJson("/api/v1/developer/webhooks/{$webhookId}");
        $deleteRes->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseMissing('webhooks', ['id' => $webhookId]);
    }
}
