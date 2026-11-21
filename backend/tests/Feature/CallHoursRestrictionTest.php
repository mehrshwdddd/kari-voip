<?php

namespace Tests\Feature;

use App\Enums\CampaignStatusEnum;
use App\Jobs\CampaignCallJob;
use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Services\Campaign\CallHoursValidator;
use App\Services\Campaign\CampaignEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class CallHoursRestrictionTest extends TestCase
{
    use RefreshDatabase;

    public function test_call_hours_validator_blocks_calls_outside_allowed_hours_or_forbidden_days(): void
    {
        Bus::fake([CampaignCallJob::class]);

        $campaign = Campaign::create([
            'name' => 'کمپین تست ساعت مجاز',
            'type' => 'audio',
            'status' => CampaignStatusEnum::DRAFT,
            'allowed_hours_start' => '09:00',
            'allowed_hours_end' => '18:00',
            'allowed_days' => ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday'],
            'total_contacts' => 1,
        ]);

        CampaignContact::create([
            'campaign_id' => $campaign->id,
            'phone_number' => '09121112233',
            'status' => 'pending',
        ]);

        $validator = new CallHoursValidator;
        $engine = app(CampaignEngine::class);

        // 1. Late night on Wednesday (22:30 Tehran time) -> blocked
        $lateWednesday = Carbon::create(2026, 11, 18, 22, 30, 0, 'Asia/Tehran');
        $this->assertFalse($validator->isWithinAllowedWindow($campaign, $lateWednesday));

        $res1 = $engine->dispatchBatch($campaign, $lateWednesday);
        $this->assertTrue($res1['outside_hours']);
        $this->assertEquals(0, $res1['dispatched']);
        Bus::assertNothingDispatched();

        // 2. Friday at 11:00 AM (day not in allowed_days) -> blocked
        $fridayMorning = Carbon::create(2026, 11, 20, 11, 0, 0, 'Asia/Tehran');
        $this->assertFalse($validator->isWithinAllowedWindow($campaign, $fridayMorning));

        // 3. Wednesday at 11:00 AM -> allowed
        $wednesdayMorning = Carbon::create(2026, 11, 18, 11, 0, 0, 'Asia/Tehran');
        $this->assertTrue($validator->isWithinAllowedWindow($campaign, $wednesdayMorning));

        $res2 = $engine->dispatchBatch($campaign, $wednesdayMorning);
        $this->assertFalse($res2['outside_hours']);
        $this->assertEquals(1, $res2['dispatched']);
        Bus::assertDispatchedTimes(CampaignCallJob::class, 1);
    }
}
