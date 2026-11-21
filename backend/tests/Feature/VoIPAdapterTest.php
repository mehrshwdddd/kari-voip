<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Telephony\Adapters\AsteriskAdapter;
use App\Services\Telephony\Adapters\GenericSipAdapter;
use App\Services\Telephony\Adapters\IssabelAdapter;
use App\Services\Telephony\TelephonyManager;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoIPAdapterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_telephony_manager_resolves_adapters_and_executes_commands(): void
    {
        $manager = app(TelephonyManager::class);

        $asterisk = $manager->adapter('asterisk');
        $this->assertInstanceOf(AsteriskAdapter::class, $asterisk);

        $originate = $asterisk->originateCall('101', '09121112233');
        $this->assertArrayHasKey('voip_call_id', $originate);
        $this->assertEquals('ringing', $originate['status']);
        $this->assertTrue($asterisk->answerCall($originate['voip_call_id']));
        $this->assertTrue($asterisk->holdCall($originate['voip_call_id'], true));
        $this->assertTrue($asterisk->muteCall($originate['voip_call_id'], true));
        $this->assertTrue($asterisk->transferCall($originate['voip_call_id'], '105'));
        $this->assertTrue($asterisk->hangupCall($originate['voip_call_id']));

        $issabel = $manager->adapter('issabel');
        $this->assertInstanceOf(IssabelAdapter::class, $issabel);
        $this->assertTrue($issabel->testConnection(['host' => '192.168.1.50'])['connected']);

        $sip = $manager->adapter('sip_webrtc');
        $this->assertInstanceOf(GenericSipAdapter::class, $sip);
        $this->assertTrue($sip->testConnection(['host' => 'sip.ir'])['connected']);
    }

    public function test_voip_config_wizard_can_save_and_test_connection(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $owner->assignRole('owner');

        $saveResponse = $this->actingAs($owner, 'sanctum')->putJson('/api/v1/voip/config', [
            'name' => 'مرکز تلفن ایزابل',
            'adapter_class' => 'issabel',
            'host' => '10.0.0.15',
            'port' => 8088,
            'username' => 'admin',
            'secret' => 'supersecret',
            'context' => 'from-internal',
            'reception_mode' => 'hybrid',
        ]);

        $saveResponse->assertOk()
            ->assertJsonPath('integration.adapter_class', 'issabel')
            ->assertJsonPath('integration.config.host', '10.0.0.15');

        $testResponse = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/voip/test-connection', [
            'adapter_class' => 'issabel',
            'host' => '10.0.0.15',
            'port' => 8088,
        ]);

        $testResponse->assertOk()
            ->assertJsonPath('connected', true);
    }
}
