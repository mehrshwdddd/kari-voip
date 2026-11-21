<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_receptionist_can_manage_live_call_lifecycle_and_screen_pop(): void
    {
        $receptionist = User::factory()->create(['status' => 'active']);
        $receptionist->assignRole('receptionist');

        $contact = Contact::create([
            'first_name' => 'پروین',
            'last_name' => 'اعتصامی',
            'phone_primary' => '09123334455',
            'credit_balance' => 1500000,
            'is_vip' => true,
        ]);

        // 1. Simulate incoming call -> Screen Pop data returned
        $inboundRes = $this->actingAs($receptionist, 'sanctum')->postJson('/api/v1/calls/simulate-inbound', [
            'caller_number' => '09123334455',
        ]);

        $inboundRes->assertStatus(201)
            ->assertJsonPath('call.status', 'ringing')
            ->assertJsonPath('call.contact.first_name', 'پروین');

        $callId = $inboundRes->json('call.id');

        // 2. Check active calls endpoint for workspace
        $activeRes = $this->actingAs($receptionist, 'sanctum')->getJson('/api/v1/calls/active');
        $activeRes->assertOk()
            ->assertJsonCount(1, 'active_calls');

        // 3. Answer call
        $answerRes = $this->actingAs($receptionist, 'sanctum')->postJson("/api/v1/calls/{$callId}/answer");
        $answerRes->assertOk()
            ->assertJsonPath('call.status', 'active');

        // 4. Hold and unhold call
        $holdRes = $this->actingAs($receptionist, 'sanctum')->postJson("/api/v1/calls/{$callId}/hold");
        $holdRes->assertOk()
            ->assertJsonPath('call.status', 'on_hold');

        // 5. Mute call
        $muteRes = $this->actingAs($receptionist, 'sanctum')->postJson("/api/v1/calls/{$callId}/mute");
        $muteRes->assertOk()
            ->assertJsonPath('call.is_muted', true);

        // 6. Start and stop recording
        $recStart = $this->actingAs($receptionist, 'sanctum')->postJson("/api/v1/calls/{$callId}/recording/start");
        $recStart->assertOk()
            ->assertJsonPath('call.is_recording', true);

        $recStop = $this->actingAs($receptionist, 'sanctum')->postJson("/api/v1/calls/{$callId}/recording/stop");
        $recStop->assertOk()
            ->assertJsonPath('call.is_recording', false);

        // 7. Add note during call
        $noteRes = $this->actingAs($receptionist, 'sanctum')->postJson("/api/v1/calls/{$callId}/note", [
            'content' => 'هماهنگی نوبت VIP انجام شد.',
            'type' => 'note',
        ]);
        $noteRes->assertStatus(201);

        // 8. Set disposition
        $dispRes = $this->actingAs($receptionist, 'sanctum')->postJson("/api/v1/calls/{$callId}/disposition", [
            'disposition' => 'ثبت نوبت / سفارش جدید',
        ]);
        $dispRes->assertOk()
            ->assertJsonPath('call.disposition', 'ثبت نوبت / سفارش جدید');

        // 9. End call
        $endRes = $this->actingAs($receptionist, 'sanctum')->postJson("/api/v1/calls/{$callId}/end");
        $endRes->assertOk()
            ->assertJsonPath('call.status', 'ended');
    }
}
