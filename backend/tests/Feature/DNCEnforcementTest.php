<?php

namespace Tests\Feature;

use App\Enums\CampaignStatusEnum;
use App\Jobs\CampaignCallJob;
use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Models\DoNotCall;
use App\Models\User;
use App\Services\Campaign\CampaignEngine;
use Carbon\Carbon;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class DNCEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_dnc_numbers_are_blocked_before_job_dispatch_and_never_dispatched(): void
    {
        Bus::fake([CampaignCallJob::class]);

        // Add blocked number to DNC list
        DoNotCall::create([
            'phone_number' => '09128889999',
            'reason' => 'درخواست عدم تماس',
            'source' => 'manual',
            'added_at' => now(),
        ]);

        $campaign = Campaign::create([
            'name' => 'کمپین تست DNC',
            'type' => 'audio',
            'status' => CampaignStatusEnum::DRAFT,
            'allowed_hours_start' => '08:00',
            'allowed_hours_end' => '21:00',
            'allowed_days' => ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'max_concurrent_calls' => 5,
            'total_contacts' => 2,
        ]);

        $blockedContact = CampaignContact::create([
            'campaign_id' => $campaign->id,
            'phone_number' => '09128889999',
            'status' => 'pending',
        ]);

        $allowedContact = CampaignContact::create([
            'campaign_id' => $campaign->id,
            'phone_number' => '09121112233',
            'status' => 'pending',
        ]);

        $engine = app(CampaignEngine::class);
        $allowedTime = Carbon::create(2026, 11, 18, 12, 0, 0, 'Asia/Tehran');

        $result = $engine->dispatchBatch($campaign, $allowedTime);

        $this->assertEquals(1, $result['dispatched']);
        $this->assertEquals(1, $result['skipped_dnc']);

        // CRITICAL ASSERTION: DNC number was NEVER dispatched to the queue
        Bus::assertDispatchedTimes(CampaignCallJob::class, 1);
        Bus::assertNotDispatched(CampaignCallJob::class, function (CampaignCallJob $job) use ($blockedContact) {
            return $job->campaignContactId === $blockedContact->id;
        });
        Bus::assertDispatched(CampaignCallJob::class, function (CampaignCallJob $job) use ($allowedContact) {
            return $job->campaignContactId === $allowedContact->id;
        });

        $this->assertDatabaseHas('campaign_contacts', [
            'id' => $blockedContact->id,
            'status' => 'skipped_dnc',
            'final_disposition' => 'opted_out',
        ]);
    }

    public function test_dtmf_9_opt_out_registers_phone_in_dnc_and_blocks_future_calls(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $owner->assignRole('owner');

        $campaign = Campaign::create([
            'name' => 'کمپین تست لغو اشتراک',
            'type' => 'ivr',
            'status' => CampaignStatusEnum::RUNNING,
            'total_contacts' => 1,
        ]);

        $campContact = CampaignContact::create([
            'campaign_id' => $campaign->id,
            'phone_number' => '09124445566',
            'status' => 'queued',
        ]);

        // Run job with simulated DTMF '9' (opt-out)
        $job = new CampaignCallJob($campaign->id, $campContact->id, null, '9');
        app()->call([$job, 'handle']);

        // Must be added to do_not_call_list
        $this->assertDatabaseHas('do_not_call_list', [
            'phone_number' => '09124445566',
            'source' => 'dtmf_opt_out',
        ]);

        $this->assertDatabaseHas('campaign_contacts', [
            'id' => $campContact->id,
            'status' => 'opted_out',
            'final_disposition' => 'opted_out',
        ]);

        // Test call to this number must now be rejected with 422
        $testCallRes = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/campaigns/{$campaign->id}/test-call", [
                'phone_number' => '09124445566',
            ]);

        $testCallRes->assertStatus(422);
    }
}
