<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIReceptionistFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_ai_receptionist_understands_persian_speech_books_appointment_and_sends_sms(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $owner->assignRole('owner');

        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/ivr/simulate', [
            'caller_number' => '09125556677',
            'utterance' => 'سلام من احمد شاملو هستم، می‌خواهم برای فردا ساعت ۱۱ یک نوبت مشاوره رزرو کنم.',
        ]);

        $response->assertOk()
            ->assertJsonPath('simulation.intent', 'book_appointment')
            ->assertJsonPath('simulation.entities.customer_name', 'احمد شاملو')
            ->assertJsonPath('simulation.entities.time_hint', '11');

        // Verify Contact profile was created/updated with extracted Persian name
        $this->assertDatabaseHas('contacts', [
            'phone_primary' => '09125556677',
            'first_name' => 'احمد',
            'last_name' => 'شاملو',
        ]);

        // Verify Appointment was automatically created
        $this->assertDatabaseCount('appointments', 1);

        // Verify SMS confirmation was logged
        $this->assertDatabaseHas('message_logs', [
            'to_address' => '09125556677',
            'status' => 'sent',
        ]);
    }

    public function test_ai_receptionist_handles_human_transfer_and_support_tickets(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $owner->assignRole('owner');

        // 1. Support ticket utterance
        $ticketRes = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/ivr/simulate', [
            'caller_number' => '09126667788',
            'utterance' => 'سلام، در سیستم خطا و مشکل فنی دارم و نیاز به ثبت تیکت پشتیبانی دارم.',
        ]);

        $ticketRes->assertOk()
            ->assertJsonPath('simulation.intent', 'support_ticket');

        $this->assertDatabaseHas('contact_notes', [
            'type' => 'task',
            'is_pinned' => true,
        ]);

        // 2. Human transfer utterance
        $transferRes = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/ivr/simulate', [
            'caller_number' => '09126667788',
            'utterance' => 'لطفاً من را به اپراتور یا منشی وصل کن',
        ]);

        $transferRes->assertOk()
            ->assertJsonPath('simulation.intent', 'human_transfer')
            ->assertJsonPath('simulation.current_node_id', 'node_human_transfer');
    }
}
