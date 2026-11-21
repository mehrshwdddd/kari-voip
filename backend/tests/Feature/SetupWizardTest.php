<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_status_and_installation_wizard_flow(): void
    {
        // 1. Initial status before installation
        $statusRes = $this->getJson('/api/v1/setup/status');
        $statusRes->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.is_installed', false)
            ->assertJsonPath('data.has_company', false)
            ->assertJsonPath('data.has_user', false);

        // 2. Perform installation via wizard
        $installRes = $this->postJson('/api/v1/setup/install', [
            'company_name' => 'کلینیک تخصصی دکتر تهرانی',
            'company_phone' => '02188889999',
            'company_address' => 'تهران، خیابان ولیعصر',
            'admin_name' => 'دکتر امید تهرانی',
            'admin_email' => 'dr.tehrani@example.com',
            'admin_phone' => '09121112233',
            'admin_password' => 'SecurePass123!',
            'branch_name' => 'شعبه مرکزی ولیعصر',
            'branch_code' => 'VALIASR',
            'voip_adapter' => 'asterisk',
            'voip_host' => '192.168.1.100',
            'voip_port' => 5038,
        ]);

        $installRes->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'user',
                    'company',
                    'branch',
                    'token',
                ],
            ]);

        $this->assertDatabaseHas('company_profile', [
            'name' => 'کلینیک تخصصی دکتر تهرانی',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'dr.tehrani@example.com',
        ]);

        $adminUser = User::where('email', 'dr.tehrani@example.com')->first();
        $this->assertTrue($adminUser->hasRole('owner'));

        // 3. Status after installation
        $statusAfter = $this->getJson('/api/v1/setup/status');
        $statusAfter->assertStatus(200)
            ->assertJsonPath('data.is_installed', true);

        // 4. Repeated installation must be rejected with 400
        $repeatedRes = $this->postJson('/api/v1/setup/install', [
            'company_name' => 'سازمان دوم',
            'company_phone' => '02111111111',
            'admin_name' => 'مدیر دوم',
            'admin_email' => 'admin2@example.com',
            'admin_phone' => '09120000000',
            'admin_password' => 'Password123!',
        ]);

        $repeatedRes->assertStatus(400)
            ->assertJsonPath('status', 'error');
    }
}
