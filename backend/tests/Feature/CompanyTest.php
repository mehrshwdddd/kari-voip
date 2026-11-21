<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_owner_can_view_and_update_company_profile(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $owner->assignRole('owner');

        $response = $this->actingAs($owner, 'sanctum')->getJson('/api/v1/company');
        $response->assertOk()
            ->assertJsonStructure(['company' => ['id', 'name', 'invoice_prefix']]);

        $updateResponse = $this->actingAs($owner, 'sanctum')->putJson('/api/v1/company', [
            'name' => 'کلینیک تخصصی مهر',
            'phone' => '02122223333',
            'email' => 'info@mehrclinic.ir',
            'invoice_prefix' => 'MEHR-',
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('company.name', 'کلینیک تخصصی مهر')
            ->assertJsonPath('company.invoice_prefix', 'MEHR-');
    }
}
