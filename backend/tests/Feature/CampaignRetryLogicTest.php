<?php

namespace Tests\Feature;

use App\Enums\CampaignStatusEnum;
use App\Jobs\CampaignCallJob;
use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Services\Campaign\RetryScheduler;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignRetryLogicTest extends TestCase
{
    use RefreshDatabase;

    public function test_retry_scheduler_retries_no_answer_and_busy_according_to_interval_array(): void
    {
        $scheduler = new RetryScheduler;

        $campaign = Campaign::create([
            'name' => 'کمپین تست تلاش مجدد',
            'type' => 'audio',
            'status' => CampaignStatusEnum::RUNNING,
            'max_retry_count' => 2,
            'retry_interval_minutes' => [15, 45],
            'total_contacts' => 1,
        ]);

        $baseTime = Carbon::create(2026, 11, 18, 10, 0, 0, 'Asia/Tehran');

        // Retryable outcomes within max_retry_count
        $this->assertTrue($scheduler->shouldRetry('no_answer', 1, 2));
        $this->assertTrue($scheduler->shouldRetry('busy', 2, 2));
        $this->assertFalse($scheduler->shouldRetry('no_answer', 3, 2)); // exceeded max_retry_count=2

        // Non-retryable outcomes must never retry
        $this->assertFalse($scheduler->shouldRetry('completed', 1, 2));
        $this->assertFalse($scheduler->shouldRetry('answered', 1, 2));
        $this->assertFalse($scheduler->shouldRetry('opted_out', 1, 2));
        $this->assertFalse($scheduler->shouldRetry('invalid_number', 1, 2));

        // Verify interval calculation: attempt 1 -> +15m, attempt 2 -> +45m
        $next1 = $scheduler->calculateNextAttemptAt($campaign, 1, $baseTime);
        $this->assertEquals('10:15', $next1->format('H:i'));

        $next2 = $scheduler->calculateNextAttemptAt($campaign, 2, $baseTime);
        $this->assertEquals('10:45', $next2->format('H:i'));
    }

    public function test_campaign_call_job_schedules_retry_on_busy_and_completes_when_retries_exhausted(): void
    {
        $campaign = Campaign::create([
            'name' => 'کمپین تست چرخه Retry',
            'type' => 'audio',
            'status' => CampaignStatusEnum::RUNNING,
            'max_retry_count' => 1,
            'retry_interval_minutes' => [20],
            'total_contacts' => 1,
        ]);

        $campContact = CampaignContact::create([
            'campaign_id' => $campaign->id,
            'phone_number' => '09123334455',
            'status' => 'queued',
            'attempt_count' => 0,
        ]);

        // Attempt 1: Busy -> should return to 'pending' with next_attempt_at set
        $job1 = new CampaignCallJob($campaign->id, $campContact->id, 'busy');
        app()->call([$job1, 'handle']);

        $campContact->refresh();
        $this->assertEquals('pending', $campContact->status);
        $this->assertEquals(1, $campContact->attempt_count);
        $this->assertNotNull($campContact->next_attempt_at);

        // Attempt 2 (retry #1): Busy again -> max_retry_count=1 exhausted -> final status 'failed'
        $job2 = new CampaignCallJob($campaign->id, $campContact->id, 'busy');
        app()->call([$job2, 'handle']);

        $campContact->refresh();
        $campaign->refresh();

        $this->assertEquals('failed', $campContact->status);
        $this->assertEquals(2, $campContact->attempt_count);
        $this->assertEquals(1, $campaign->completed_contacts);
        $this->assertEquals(1, $campaign->failed_count);
        $this->assertEquals(CampaignStatusEnum::COMPLETED, $campaign->status);
    }
}
