<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_user_can_login_with_email_and_password(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@voicereach.local',
            'password' => bcrypt('secret123'),
            'status' => 'active',
        ]);
        $user->assignRole('owner');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@voicereach.local',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email', 'roles', 'permissions'],
            ]);
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'admin@voicereach.local',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@voicereach.local',
            'password' => 'wrong-pass',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_otp_request_and_verification_workflow(): void
    {
        $user = User::factory()->create([
            'phone' => '09123456789',
            'status' => 'active',
        ]);
        $user->assignRole('owner');

        // Request OTP
        $reqResponse = $this->postJson('/api/v1/auth/login/otp/request', [
            'phone' => '09123456789',
        ]);
        $reqResponse->assertOk()->assertJson(['success' => true]);

        $cachedOtp = Cache::get('otp_login_09123456789');
        $this->assertNotEmpty($cachedOtp);

        // Verify OTP
        $verifyResponse = $this->postJson('/api/v1/auth/login/otp/verify', [
            'phone' => '09123456789',
            'code' => $cachedOtp,
        ]);

        $verifyResponse->assertOk()
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_authenticated_user_can_get_profile_and_logout(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('owner');
        $token = $user->createToken('test_token')->plainTextToken;

        // Get profile
        $profileResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');
        $profileResponse->assertOk()
            ->assertJsonPath('user.email', $user->email);

        // Logout
        $logoutResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');
        $logoutResponse->assertOk()->assertJson(['success' => true]);
    }
}
