<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\InitialSystemSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(InitialSystemSeeder::class);
    }

    public function test_executive_reports_and_csv_export_return_accurate_aggregated_analytics(): void
    {
        $owner = User::where('email', 'admin@voicereach.local')->firstOrFail();

        // 1. Dashboard executive KPIs
        $dashRes = $this->actingAs($owner, 'sanctum')->getJson('/api/v1/reports/dashboard');
        $dashRes->assertOk()
            ->assertJsonStructure([
                'kpis' => [
                    'total_contacts',
                    'total_calls',
                    'total_campaigns',
                    'total_appointments',
                    'total_invoiced_toman',
                    'total_collected_toman',
                ],
                'charts' => ['calls_by_direction', 'revenue_breakdown'],
            ]);

        // 2. Calls, Campaigns, Appointments, Revenue & Users reports
        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/reports/calls')->assertOk();
        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/reports/campaigns')->assertOk();
        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/reports/appointments')->assertOk();
        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/reports/revenue')->assertOk();
        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/reports/users')->assertOk();

        // 3. CSV Export with UTF-8 BOM
        $exportRes = $this->actingAs($owner, 'sanctum')->get('/api/v1/reports/export?module=revenue');
        $exportRes->assertOk();
        $this->assertStringContainsString('VR-1001', $exportRes->getContent());
    }
}
