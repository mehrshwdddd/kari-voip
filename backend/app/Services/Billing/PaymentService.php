<?php

namespace App\Services\Billing;

use App\Models\AccountingLog;
use App\Models\Cheque;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PaymentService
{
    /**
     * Record a payment against an invoice and automatically update accounting logs and customer debt/wallet.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordPayment(Invoice $invoice, array $data, ?int $userId = null): Payment
    {
        return DB::transaction(function () use ($invoice, $data, $userId): Payment {
            $amount = round((float) $data['amount'], 2);
            $method = (string) ($data['method'] ?? 'card');

            $contact = Contact::findOrFail($invoice->contact_id);

            // If paying from customer wallet (credit_balance), verify sufficient balance
            if ($method === 'credit') {
                if ((float) $contact->credit_balance < $amount) {
                    throw ValidationException::withMessages([
                        'amount' => ['موجودی کیف پول مشتری برای پرداخت این مبلغ کافی نیست.'],
                    ]);
                }
                $contact->update([
                    'credit_balance' => round(((float) $contact->credit_balance) - $amount, 2),
                ]);
            }

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'contact_id' => $contact->id,
                'amount' => $amount,
                'method' => $method,
                'gateway_name' => $data['gateway_name'] ?? ($method === 'gateway' ? 'zarinpal' : null),
                'gateway_ref' => $data['gateway_ref'] ?? ('REF-'.strtoupper(Str::random(8))),
                'gateway_response' => $data['gateway_response'] ?? ['verified' => true],
                'status' => 'completed',
                'paid_at' => now(),
                'notes' => $data['notes'] ?? null,
                'processed_by' => $userId,
            ]);

            if ($method === 'cheque' && ! empty($data['cheque_number'])) {
                Cheque::create([
                    'payment_id' => $payment->id,
                    'contact_id' => $contact->id,
                    'cheque_number' => (string) $data['cheque_number'],
                    'bank_name' => (string) ($data['bank_name'] ?? 'بانک ملت'),
                    'branch_name' => $data['branch_name'] ?? null,
                    'amount' => $amount,
                    'due_date' => $data['due_date'] ?? now()->addDays(30)->toDateString(),
                    'status' => 'pending',
                ]);
            }

            $newPaid = round(((float) $invoice->paid_amount) + $amount, 2);
            $newRemaining = max(0.0, round(((float) $invoice->total_amount) - $newPaid, 2));
            $newStatus = $newRemaining <= 0.0 ? 'paid' : 'partial';

            $invoice->update([
                'paid_amount' => $newPaid,
                'remaining_amount' => $newRemaining,
                'status' => $newStatus,
                'paid_at' => $newStatus === 'paid' ? now() : $invoice->paid_at,
            ]);

            // Auto-create Accounting Log Credit entry & reduce Contact total_debt
            $newDebt = max(0.0, round(((float) $contact->total_debt) - $amount, 2));
            $contact->update(['total_debt' => $newDebt]);

            AccountingLog::create([
                'branch_id' => $invoice->branch_id,
                'reference_type' => 'payment',
                'reference_id' => $payment->id,
                'contact_id' => $contact->id,
                'debit' => 0,
                'credit' => $amount,
                'balance_after' => $newDebt,
                'description' => "دریافت وجه فاکتور {$invoice->invoice_number} (روش: {$method})",
                'created_by' => $userId,
                'created_at' => now(),
            ]);

            return $payment;
        });
    }

    /**
     * Generate a payment link for Iranian gateways (ZarinPal / IDPay).
     *
     * @return array{gateway: string, authority: string, payment_url: string, amount: float}
     */
    public function createGatewayPaymentLink(Invoice $invoice, string $gateway = 'zarinpal'): array
    {
        $authority = 'A00000000000000000000000000'.random_int(100000000, 999999999);
        $amount = (float) $invoice->remaining_amount;

        $url = strtolower($gateway) === 'idpay'
            ? "https://idpay.ir/p/ws/{$authority}"
            : "https://www.zarinpal.com/pg/StartPay/{$authority}";

        return [
            'gateway' => $gateway,
            'authority' => $authority,
            'payment_url' => $url,
            'amount' => $amount,
        ];
    }
}
