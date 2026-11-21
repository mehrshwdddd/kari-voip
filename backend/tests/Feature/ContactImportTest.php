<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_csv_contact_list_importer_normalizes_numbers_detects_duplicates_and_skips_invalids(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $owner->assignRole('owner');

        $csvContent = implode("\n", [
            'phone,first_name,last_name',
            '09121112233,نیما,یوشیج',
            '+989121112233,نیما,تکراری', // Duplicate after normalization
            '989351112233,فروغ,فرخزاد',
            'invalid_phone_999,خطا,نامعتبر', // Invalid phone
            '02188887766,شرکت,توسعه',
        ]);

        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/contact-lists', [
            'name' => 'لیست مشتریان ویژه آبان',
            'csv_content' => $csvContent,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('contact_list.valid_count', 3)
            ->assertJsonPath('duplicate_count', 1)
            ->assertJsonPath('invalid_count', 1);

        $this->assertDatabaseHas('contacts', [
            'phone_primary' => '09121112233',
            'first_name' => 'نیما',
        ]);

        $this->assertDatabaseHas('contacts', [
            'phone_primary' => '09351112233',
            'first_name' => 'فروغ',
        ]);
    }
}
