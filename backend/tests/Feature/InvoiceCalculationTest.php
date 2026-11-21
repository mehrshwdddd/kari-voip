<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_invoice_calculation_engine_and_payment_accounting_logs(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $owner->assignRole('owner');

        $contact = Contact::create([
            'first_name' => 'کیان',
            'last_name' => 'افشار',
            'phone_primary' => '09125557788',
            'credit_balance' => 500000,
            'total_debt' => 0,
        ]);

        // Item 1: 2 * 500,000 = 1,000,000 | 10% discount = 100,000 | taxable = 900,000 | 9% tax = 81,000 | total = 981,000
        $createRes = $this->actingAs($owner, 'sanctum')->postJson('/api/v1/invoices', [
            'contact_id' => $contact->id,
            'type' => 'service',
            'items' => [
                [
                    'name' => 'پکیج مشاوره سازمانی',
                    'quantity' => 2,
                    'unit_price' => 500000,
                    'discount_percent' => 10,
                    'tax_percent' => 9,
                ],
            ],
        ]);

        $createRes->assertStatus(201);
        $invoiceId = $createRes->json('invoice.id');

        $this->assertEquals(1000000.0, (float) $createRes->json('invoice.subtotal'));
        $this->assertEquals(100000.0, (float) $createRes->json('invoice.discount_amount'));
        $this->assertEquals(81000.0, (float) $createRes->json('invoice.tax_amount'));
        $this->assertEquals(981000.0, (float) $createRes->json('invoice.total_amount'));
        $this->assertEquals(981000.0, (float) $createRes->json('invoice.remaining_amount'));

        // Verify accounting log debit & contact debt updated
        $contact->refresh();
        $this->assertEquals(981000.0, (float) $contact->total_debt);
        $this->assertDatabaseHas('accounting_logs', [
            'reference_type' => 'invoice',
            'reference_id' => $invoiceId,
            'debit' => 981000,
        ]);

        // Record partial payment from customer wallet ('credit' = 400,000)
        $pay1 = $this->actingAs($owner, 'sanctum')->postJson("/api/v1/invoices/{$invoiceId}/payment", [
            'amount' => 400000,
            'method' => 'credit',
        ]);

        $pay1->assertStatus(201)
            ->assertJsonPath('invoice.status', 'partial');

        $contact->refresh();
        $this->assertEquals(100000.0, (float) $contact->credit_balance);
        $this->assertEquals(581000.0, (float) $contact->total_debt);

        // Record remaining payment via card (581,000) -> status becomes 'paid'
        $pay2 = $this->actingAs($owner, 'sanctum')->postJson("/api/v1/invoices/{$invoiceId}/payment", [
            'amount' => 581000,
            'method' => 'card',
        ]);

        $pay2->assertStatus(201)
            ->assertJsonPath('invoice.status', 'paid');

        $contact->refresh();
        $this->assertEquals(0.0, (float) $contact->total_debt);
    }
}
