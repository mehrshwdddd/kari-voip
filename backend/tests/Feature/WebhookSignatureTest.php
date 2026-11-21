<?php

namespace Tests\Feature;

use App\Models\Contact;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookSignatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_webhook_rejects_missing_or_invalid_hmac_signature(): void
    {
        $payload = [
            'event' => 'call.started',
            'voip_call_id' => 'ast_9991',
            'caller_number' => '09121112233',
        ];

        // No signature headers -> 401
        $res1 = $this->postJson('/api/v1/calls/webhook', $payload);
        $res1->assertStatus(401);

        // Wrong HMAC -> 401
        $res2 = $this->withHeaders([
            'X-VoIP-Timestamp' => (string) time(),
            'X-VoIP-Signature' => 'invalid_signature_hash',
        ])->postJson('/api/v1/calls/webhook', $payload);
        $res2->assertStatus(401);

        // Expired timestamp (> 5 min ago) -> 401
        $oldTimestamp = (string) (time() - 600);
        $rawJson = json_encode($payload);
        $secret = config('services.voip.webhook_secret', env('VOIP_WEBHOOK_SECRET', 'voicereach_voip_secret'));
        $expiredSig = hash_hmac('sha256', "{$oldTimestamp}.{$rawJson}", $secret);

        $res3 = $this->call(
            'POST',
            '/api/v1/calls/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_VOIP_TIMESTAMP' => $oldTimestamp,
                'HTTP_X_VOIP_SIGNATURE' => $expiredSig,
            ],
            $rawJson
        );
        $res3->assertStatus(401);
    }

    public function test_webhook_accepts_valid_hmac_and_enforces_idempotency(): void
    {
        $contact = Contact::create([
            'first_name' => 'فرهاد',
            'last_name' => 'کریمی',
            'phone_primary' => '09127778899',
        ]);

        $payload = [
            'event' => 'call.started',
            'voip_call_id' => 'ast_valid_100',
            'caller_number' => '09127778899',
            'callee_number' => '02188889999',
            'direction' => 'inbound',
        ];

        $timestamp = (string) time();
        $rawJson = json_encode($payload);
        $secret = (string) config('services.voip.webhook_secret', env('VOIP_WEBHOOK_SECRET', 'voicereach_voip_secret'));
        $validSig = hash_hmac('sha256', "{$timestamp}.{$rawJson}", $secret);

        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_VOIP_TIMESTAMP' => $timestamp,
            'HTTP_X_VOIP_SIGNATURE' => $validSig,
            'HTTP_X_VOIP_DELIVERY_ID' => 'deliv_unique_01',
        ];

        $response = $this->call('POST', '/api/v1/calls/webhook', [], [], [], $headers, $rawJson);
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'ringing');

        $this->assertDatabaseHas('calls', [
            'voip_call_id' => 'ast_valid_100',
            'contact_id' => $contact->id,
        ]);

        // Duplicate delivery ID must be idempotently ignored
        $dupResponse = $this->call('POST', '/api/v1/calls/webhook', [], [], [], $headers, $rawJson);
        $dupResponse->assertOk()
            ->assertJsonPath('duplicate_ignored', true);
    }
}
