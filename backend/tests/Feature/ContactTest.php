<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Contact;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_owner_can_create_and_view_contacts(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $owner->assignRole('owner');

        // Create contact
        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/contacts', [
            'first_name' => 'رضا',
            'last_name' => 'محمدی',
            'phone_primary' => '09121113344',
            'status' => 'active',
            'source' => 'call',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('contact.first_name', 'رضا');

        $contactId = $response->json('contact.id');

        // View contact
        $viewResponse = $this->actingAs($owner, 'sanctum')->getJson("/api/v1/contacts/{$contactId}");
        $viewResponse->assertOk()
            ->assertJsonPath('contact.phone_primary', '09121113344');
    }

    public function test_bola_protection_user_cannot_access_other_branch_contact(): void
    {
        $branchA = Branch::create(['name' => 'شعبه شمال', 'code' => 'NORTH']);
        $branchB = Branch::create(['name' => 'شعبه جنوب', 'code' => 'SOUTH']);

        // Receptionist belonging to Branch A
        $receptionistA = User::factory()->create([
            'branch_id' => $branchA->id,
            'status' => 'active',
        ]);
        $receptionistA->assignRole('receptionist');

        // Contact strictly tied to Branch B
        $contactB = Contact::create([
            'branch_id' => $branchB->id,
            'first_name' => 'علی',
            'last_name' => 'اکبری',
            'phone_primary' => '09129998877',
        ]);

        // Receptionist A tries to access Contact B
        $response = $this->actingAs($receptionistA, 'sanctum')->getJson("/api/v1/contacts/{$contactB->id}");

        // Must be forbidden (403) due to BOLA policy enforcement
        $response->assertStatus(403);
    }

    public function test_can_add_note_to_contact(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $owner->assignRole('owner');

        $contact = Contact::create([
            'first_name' => 'مریم',
            'last_name' => 'صادقی',
            'phone_primary' => '09125556677',
        ]);

        $response = $this->actingAs($owner, 'sanctum')->postJson("/api/v1/contacts/{$contact->id}/notes", [
            'content' => 'تماس گرفته شد و پیگیری برای جلسه فردا هماهنگ گردید.',
            'type' => 'follow_up',
            'is_pinned' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('note.content', 'تماس گرفته شد و پیگیری برای جلسه فردا هماهنگ گردید.');

        // Timeline check
        $timelineResponse = $this->actingAs($owner, 'sanctum')->getJson("/api/v1/contacts/{$contact->id}/timeline");
        $timelineResponse->assertOk()
            ->assertJsonCount(1, 'timeline');
    }
}
