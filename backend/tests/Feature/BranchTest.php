<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_owner_can_list_and_create_branches(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $owner->assignRole('owner');

        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/branches', [
            'name' => 'شعبه غرب',
            'code' => 'WEST',
            'phone' => '02144445555',
            'address' => 'تهران، صادقیه',
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('branch.name', 'شعبه غرب')
            ->assertJsonPath('branch.code', 'WEST');

        $listResponse = $this->actingAs($owner, 'sanctum')->getJson('/api/v1/branches');
        $listResponse->assertOk()
            ->assertJsonCount(1, 'branches');
    }

    public function test_branch_manager_can_only_view_own_branch(): void
    {
        $branch1 = Branch::create(['name' => 'شعبه ۱', 'code' => 'B1']);
        $branch2 = Branch::create(['name' => 'شعبه ۲', 'code' => 'B2']);

        $manager1 = User::factory()->create([
            'branch_id' => $branch1->id,
            'status' => 'active',
        ]);
        $manager1->assignRole('branch_manager');

        $response = $this->actingAs($manager1, 'sanctum')->getJson('/api/v1/branches');
        $response->assertOk()
            ->assertJsonCount(1, 'branches')
            ->assertJsonPath('branches.0.code', 'B1');
    }
}
