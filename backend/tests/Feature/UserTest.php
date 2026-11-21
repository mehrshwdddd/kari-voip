<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_owner_can_create_and_manage_users(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $owner->assignRole('owner');

        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/users', [
            'name' => 'کارشناس پذیرش ۱',
            'email' => 'reception@voicereach.local',
            'phone' => '09123334455',
            'password' => 'secret1234',
            'role' => 'receptionist',
            'status' => 'active',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.name', 'کارشناس پذیرش ۱')
            ->assertJsonPath('user.email', 'reception@voicereach.local');

        $newUser = User::where('email', 'reception@voicereach.local')->first();
        $this->assertTrue($newUser->hasRole('receptionist'));

        // Reset password
        $resetResponse = $this->actingAs($owner, 'sanctum')->postJson("/api/v1/users/{$newUser->id}/reset-password", [
            'password' => 'newpassword123',
        ]);
        $resetResponse->assertOk()->assertJson(['success' => true]);
    }
}
