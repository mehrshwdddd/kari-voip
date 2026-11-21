<?php

namespace Tests\Feature;

use App\Enums\CallDirectionEnum;
use App\Enums\CallStatusEnum;
use App\Enums\CallTypeEnum;
use App\Models\Branch;
use App\Models\Call;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordingAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_unauthorized_user_cannot_access_call_recording_and_receives_403(): void
    {
        $branchA = Branch::create(['name' => 'شعبه الف', 'code' => 'BRA']);
        $branchB = Branch::create(['name' => 'شعبه ب', 'code' => 'BRB']);

        $call = Call::create([
            'branch_id' => $branchB->id,
            'caller_number' => '09121112233',
            'callee_number' => '02188889999',
            'direction' => CallDirectionEnum::INBOUND,
            'type' => CallTypeEnum::MANUAL,
            'status' => CallStatusEnum::ENDED,
            'recording_path' => 'recordings/test_secure.wav',
        ]);

        // 1. Receptionist lacks 'calls.recording.listen' permission -> 403
        $receptionist = User::factory()->create([
            'branch_id' => $branchB->id,
            'status' => 'active',
        ]);
        $receptionist->assignRole('receptionist');

        $res1 = $this->actingAs($receptionist, 'sanctum')->getJson("/api/v1/calls/{$call->id}/recording");
        $res1->assertStatus(403);

        // 2. Branch Manager of Branch A has permission, but call belongs to Branch B (BOLA) -> 403
        $managerA = User::factory()->create([
            'branch_id' => $branchA->id,
            'status' => 'active',
        ]);
        $managerA->assignRole('branch_manager');

        $res2 = $this->actingAs($managerA, 'sanctum')->getJson("/api/v1/calls/{$call->id}/recording");
        $res2->assertStatus(403);

        // 3. Direct unsigned stream URL access -> 403
        $res3 = $this->get("/api/v1/calls/{$call->id}/recording/stream");
        $res3->assertStatus(403);
    }

    public function test_authorized_user_receives_signed_url_and_can_stream_recording(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $owner->assignRole('owner');

        $call = Call::create([
            'caller_number' => '09121112233',
            'callee_number' => '02188889999',
            'direction' => CallDirectionEnum::INBOUND,
            'type' => CallTypeEnum::MANUAL,
            'status' => CallStatusEnum::ENDED,
            'recording_path' => 'recordings/test_authorized.wav',
        ]);

        $response = $this->actingAs($owner, 'sanctum')->getJson("/api/v1/calls/{$call->id}/recording");
        $response->assertOk()
            ->assertJsonStructure(['recording_url', 'expires_in_seconds']);

        $signedUrl = $response->json('recording_url');

        // Streaming with the valid signed URL succeeds
        $streamRes = $this->get($signedUrl);
        $streamRes->assertOk()
            ->assertHeader('Content-Type', 'audio/wav');
    }
}
