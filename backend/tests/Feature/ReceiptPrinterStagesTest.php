<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptPrinterStagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_invoice_receipt_endpoint_returns_dqnamo_thermal_printer_stages_and_pdf(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $owner->assignRole('owner');

        $contact = Contact::create([
            'first_name' => 'مهران',
            'last_name' => 'مدیری',
            'phone_primary' => '09123339900',
        ]);

        $createRes = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/invoices', [
            'contact_id' => $contact->id,
            'items' => [
                [
                    'name' => 'نصب و راه‌اندازی ماژول تلفنی',
                    'quantity' => 1,
                    'unit_price' => 1200000,
                    'discount_percent' => 0,
                    'tax_percent' => 9,
                ],
            ],
        ]);

        $invoiceId = $createRes->json('invoice.id');

        // 1. Check dqnamo receipt printer stages
        $receiptRes = $this->actingAs($owner, 'sanctum')->getJson("/api/v1/invoices/{$invoiceId}/receipt");
        $receiptRes->assertOk()
            ->assertJsonPath('printer_engine', 'dqnamo-thermal-v2')
            ->assertJsonPath('duration_ms', 800)
            ->assertJsonCount(7, 'stages')
            ->assertJsonPath('stages.0.stage', 'initializing')
            ->assertJsonPath('stages.6.stage', 'completed')
            ->assertJsonPath('receipt.customer_name', 'مهران مدیری');

        // 2. Check Printable Persian HTML/PDF endpoint
        $pdfRes = $this->actingAs($owner, 'sanctum')->get("/api/v1/invoices/{$invoiceId}/pdf");
        $pdfRes->assertOk();
        $this->assertStringContainsString('نصب و راه‌اندازی ماژول تلفنی', $pdfRes->getContent());
    }
}
