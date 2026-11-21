<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Holiday;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffBreak;
use App\Models\StaffSchedule;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_availability_engine_prevents_overlapping_appointments_breaks_and_holidays(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $owner->assignRole('owner');

        $contact = Contact::create([
            'first_name' => 'بهرام',
            'last_name' => 'رادان',
            'phone_primary' => '09121114455',
        ]);

        $service = Service::create([
            'name' => 'مشاوره ۴۵ دقیقه‌ای',
            'duration_minutes' => 45,
            'buffer_minutes' => 15, // 45 + 15 = 60 min total window
            'price' => 500000,
            'is_active' => true,
        ]);

        $staff = Staff::create([
            'name' => 'دکتر نادری',
            'title' => 'متخصص',
            'is_active' => true,
        ]);

        StaffSchedule::create([
            'staff_id' => $staff->id,
            'day_of_week' => 'saturday',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_working' => true,
        ]);

        StaffBreak::create([
            'staff_id' => $staff->id,
            'day_of_week' => 'saturday',
            'start_time' => '13:00',
            'end_time' => '14:00',
            'label' => 'ناهار و نماز',
        ]);

        // 1. Valid booking on Saturday 2026-11-21 at 10:00 (ends 10:45 + 15m buffer = 11:00)
        $res1 = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/appointments', [
            'contact_id' => $contact->id,
            'service_id' => $service->id,
            'staff_id' => $staff->id,
            'starts_at' => '2026-11-21 10:00:00',
        ]);

        $res1->assertStatus(201)
            ->assertJsonPath('appointment.status', 'confirmed');

        // Verify 24h and 1h reminders were automatically scheduled
        $this->assertDatabaseCount('appointment_reminders', 2);

        // 2. Overlapping booking at 10:50 (falls inside the 15-minute buffer window) -> 422
        $resOverlap = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/appointments', [
            'contact_id' => $contact->id,
            'service_id' => $service->id,
            'staff_id' => $staff->id,
            'starts_at' => '2026-11-21 10:50:00',
        ]);
        $resOverlap->assertStatus(422);

        // 3. Booking during staff break (13:15) -> 422
        $resBreak = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/appointments', [
            'contact_id' => $contact->id,
            'service_id' => $service->id,
            'staff_id' => $staff->id,
            'starts_at' => '2026-11-21 13:15:00',
        ]);
        $resBreak->assertStatus(422);

        // 4. Booking on an official holiday -> 422
        Holiday::create([
            'name' => 'تعطیل رسمی',
            'date' => '2026-11-28',
        ]);

        $resHoliday = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/appointments', [
            'contact_id' => $contact->id,
            'service_id' => $service->id,
            'staff_id' => $staff->id,
            'starts_at' => '2026-11-28 10:00:00',
        ]);
        $resHoliday->assertStatus(422);
    }
}
