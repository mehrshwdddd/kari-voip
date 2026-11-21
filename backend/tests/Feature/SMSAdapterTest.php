<?php

namespace Tests\Feature;

use App\Models\SmsProvider;
use App\Models\User;
use App\Services\Messaging\MessageTemplateEngine;
use App\Services\Messaging\SmsManager;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SMSAdapterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_template_engine_extracts_variables_and_sends_sms_with_failover(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $owner->assignRole('owner');

        $engine = new MessageTemplateEngine;
        $vars = $engine->extractVariables('سلام {customer_name}، نوبت شما در تاریخ {{appointment_date}} ثبت شد.');
        $this->assertEquals(['customer_name', 'appointment_date'], $vars);

        // 1. Create template via API
        $tplRes = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/messages/templates', [
            'name' => 'قالب خوش‌آمدگویی',
            'category' => 'custom',
            'body' => 'مشتری گرامی {customer_name}، به سامانه {company_name} خوش آمدید.',
        ]);

        $tplRes->assertStatus(201)
            ->assertJsonPath('template.variables', ['customer_name', 'company_name']);

        $templateId = $tplRes->json('template.id');

        // 2. Send SMS using template variables
        $sendRes = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/messages/send', [
            'to_address' => '09121112233',
            'template_id' => $templateId,
            'variables' => [
                'customer_name' => 'سهراب سپهری',
                'company_name' => 'ویس‌ریچ',
            ],
        ]);

        $sendRes->assertStatus(201)
            ->assertJsonPath('message_log.status', 'sent')
            ->assertJsonPath('message_log.body', 'مشتری گرامی سهراب سپهری، به سامانه ویس‌ریچ خوش آمدید.');

        // 3. Test Failover: Primary Kavenegar returns HTTP 500 -> automatically fails over to Ghasedak!
        Http::fake([
            'api.kavenegar.com/*' => Http::response(['error' => 'Gateway Down'], 500),
            'api.ghasedak.me/*' => Http::response(['items' => ['ghs_failover_99']], 200),
        ]);

        SmsProvider::create([
            'name' => 'Kavenegar Primary',
            'adapter_class' => 'kavenegar',
            'credentials' => ['api_key' => 'live_key_kvn'],
            'sender_number' => '10008663',
            'is_active' => true,
            'priority' => 1,
        ]);

        SmsProvider::create([
            'name' => 'Ghasedak Backup',
            'adapter_class' => 'ghasedak',
            'credentials' => ['api_key' => 'live_key_ghs'],
            'sender_number' => '30005088',
            'is_active' => true,
            'priority' => 2,
        ]);

        $smsManager = app(SmsManager::class);
        $failoverLog = $smsManager->sendSms('09127778899', 'تست پایداری و Failover پیامک');

        $this->assertEquals('sent', $failoverLog->status);
        $this->assertEquals('ghasedak', $failoverLog->provider);
        $this->assertEquals('ghs_failover_99', $failoverLog->provider_ref);
    }
}
