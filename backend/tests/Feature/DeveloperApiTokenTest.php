<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeveloperApiTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_can_create_list_and_revoke_sanctum_api_tokens(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $owner->assignRole('owner');

        // 1. Create token
        $createResponse = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/developer/tokens', [
            'name' => 'CRM Integration Key',
            'abilities' => ['contacts:read', 'calls:read'],
        ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => ['token', 'plain_text_token'],
            ]);

        $plainTextToken = $createResponse->json('data.plain_text_token');
        $tokenId = $createResponse->json('data.token.id');

        // 2. List tokens
        $listResponse = $this->actingAs($owner, 'sanctum')->getJson('/api/v1/developer/tokens');
        $listResponse->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'CRM Integration Key');

        // 3. Make authenticated request using Bearer token directly
        $this->app['auth']->forgetGuards();
        $bearerResponse = $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->getJson('/api/v1/developer/system-status');

        $bearerResponse->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.app_name', 'VoiceReach OS')
            ->assertJsonPath('data.system_healthy', true);

        // 4. Revoke token
        $revokeResponse = $this->actingAs($owner, 'sanctum')->deleteJson('/api/v1/developer/tokens/'.$tokenId);
        $revokeResponse->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // 5. Ensure revoked token no longer authenticates
        $this->app['auth']->forgetGuards();
        $afterRevokeResponse = $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->getJson('/api/v1/developer/system-status');

        $afterRevokeResponse->assertStatus(401);
    }

    public function test_can_query_audit_logs_with_filtering(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $owner->assignRole('owner');

        AuditLog::create([
            'user_id' => $owner->id,
            'event_type' => 'call_transfer',
            'model_type' => 'App\Models\Call',
            'model_id' => 12,
            'old_values' => ['operator' => 'علی'],
            'new_values' => ['operator' => 'مریم'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($owner, 'sanctum')->getJson('/api/v1/developer/audit-logs?event_type=call_transfer');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.event_type', 'call_transfer')
            ->assertJsonPath('data.data.0.model_id', 12);
    }
}
