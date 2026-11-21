<?php

namespace App\Services\Billing;

use App\Models\AccountingLog;
use App\Models\CompanyProfile;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;

final class InvoiceService
{
    /**
     * Calculate line item totals and overall invoice subtotal, discount, tax, and total amount.
     *
     * @param  array<int, array<string, mixed>>  $rawItems
     * @return array{
     *   subtotal: float,
     *   discount_amount: float,
     *   tax_amount: float,
     *   total_amount: float,
     *   items: array<int, array<string, mixed>>
     * }
     */
    public function calculateTotals(array $rawItems): array
    {
        $subtotal = 0.0;
        $totalDiscount = 0.0;
        $totalTax = 0.0;
        $grandTotal = 0.0;
        $calculatedItems = [];

        foreach ($rawItems as $item) {
            $qty = max(0.01, (float) ($item['quantity'] ?? 1));
            $unitPrice = max(0.0, (float) ($item['unit_price'] ?? 0));
            $lineGross = round($qty * $unitPrice, 2);

            $discountPercent = max(0.0, min(100.0, (float) ($item['discount_percent'] ?? 0)));
            $discountAmount = isset($item['discount_amount']) && (float) $item['discount_amount'] > 0
                ? round((float) $item['discount_amount'], 2)
                : round($lineGross * ($discountPercent / 100.0), 2);

            $taxable = max(0.0, $lineGross - $discountAmount);
            $taxPercent = array_key_exists('tax_percent', $item)
                ? max(0.0, (float) $item['tax_percent'])
                : 9.0;
            $taxAmount = round($taxable * ($taxPercent / 100.0), 2);
            $lineTotal = round($taxable + $taxAmount, 2);

            $subtotal += $lineGross;
            $totalDiscount += $discountAmount;
            $totalTax += $taxAmount;
            $grandTotal += $lineTotal;

            $calculatedItems[] = [
                'name' => (string) ($item['name'] ?? 'خدمت / کالا'),
                'description' => $item['description'] ?? null,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'tax_percent' => $taxPercent,
                'tax_amount' => $taxAmount,
                'total_amount' => $lineTotal,
                'service_id' => $item['service_id'] ?? null,
            ];
        }

        return [
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($totalDiscount, 2),
            'tax_amount' => round($totalTax, 2),
            'total_amount' => round($grandTotal, 2),
            'items' => $calculatedItems,
        ];
    }

    /**
     * Create an invoice with items and automatically record an accounting log debit entry.
     *
     * @param  array<string, mixed>  $data
     */
    public function createInvoice(array $data, ?int $userId = null): Invoice
    {
        return DB::transaction(function () use ($data, $userId): Invoice {
            $company = CompanyProfile::firstOrCreate(['id' => 1], [
                'name' => 'VoiceReach OS',
                'invoice_prefix' => 'VR-',
                'invoice_counter' => 1000,
            ]);

            $invoiceNumber = ! empty($data['invoice_number'])
                ? (string) $data['invoice_number']
                : ($company->invoice_prefix.$company->invoice_counter);

            $company->increment('invoice_counter');

            $totals = $this->calculateTotals($data['items'] ?? []);

            $invoice = Invoice::create([
                'branch_id' => $data['branch_id'] ?? null,
                'invoice_number' => $invoiceNumber,
                'contact_id' => $data['contact_id'],
                'status' => $data['status'] ?? 'sent',
                'type' => $data['type'] ?? 'service',
                'issue_date' => $data['issue_date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'] ?? now()->addDays(7)->toDateString(),
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount_amount'],
                'tax_amount' => $totals['tax_amount'],
                'total_amount' => $totals['total_amount'],
                'paid_amount' => 0,
                'remaining_amount' => $totals['total_amount'],
                'currency' => 'IRR',
                'notes' => $data['notes'] ?? null,
                'appointment_id' => $data['appointment_id'] ?? null,
                'created_by' => $userId,
                'sent_at' => now(),
            ]);

            foreach ($totals['items'] as $calcItem) {
                InvoiceItem::create(array_merge($calcItem, [
                    'invoice_id' => $invoice->id,
                    'created_at' => now(),
                ]));
            }

            // Auto-create Accounting Log Debit entry & update Contact total_debt
            $contact = Contact::findOrFail($invoice->contact_id);
            $newDebt = round(((float) $contact->total_debt) + $totals['total_amount'], 2);
            $contact->update(['total_debt' => $newDebt]);

            AccountingLog::create([
                'branch_id' => $invoice->branch_id,
                'reference_type' => 'invoice',
                'reference_id' => $invoice->id,
                'contact_id' => $contact->id,
                'debit' => $totals['total_amount'],
                'credit' => 0,
                'balance_after' => $newDebt,
                'description' => "صدور فاکتور شماره {$invoice->invoice_number}",
                'created_by' => $userId,
                'created_at' => now(),
            ]);

            return $invoice->load(['items', 'contact', 'branch', 'payments']);
        });
    }

    /**
     * Build the structured payload and animation stages for the dqnamo InvoiceReceiptPrinter component.
     *
     * @return array<string, mixed>
     */
    public function getReceiptPrinterPayload(Invoice $invoice): array
    {
        $invoice->loadMissing(['items', 'contact', 'branch', 'payments']);
        $company = CompanyProfile::first();

        return [
            'printer_engine' => 'dqnamo-thermal-v2',
            'duration_ms' => 800,
            'paper_width_mm' => 80,
            'stages' => [
                ['stage' => 'initializing', 'label' => 'آماده‌سازی چاپگر حرارتی...', 'progress' => 10],
                ['stage' => 'printing_header', 'label' => 'چاپ سربرگ و مشخصات سازمان...', 'progress' => 25],
                ['stage' => 'printing_items', 'label' => 'چاپ ردیف‌های خدمات و اقلام...', 'progress' => 55],
                ['stage' => 'printing_totals', 'label' => 'محاسبه جمع کل، تخفیف و مالیات...', 'progress' => 75],
                ['stage' => 'printing_qr', 'label' => 'ثبت بارکد رهگیری و مهر دیجیتال...', 'progress' => 90],
                ['stage' => 'cutting_paper', 'label' => 'برش خودکار کاغذ رسید...', 'progress' => 98],
                ['stage' => 'completed', 'label' => 'رسید صادر و آماده تحویل است', 'progress' => 100],
            ],
            'receipt' => [
                'company_name' => $company?->name ?? 'VoiceReach OS',
                'company_phone' => $company?->phone ?? '02188889999',
                'branch_name' => $invoice->branch?->name ?? 'شعبه مرکزی',
                'invoice_number' => $invoice->invoice_number,
                'issue_date' => $invoice->issue_date?->toDateString(),
                'status' => $invoice->status,
                'customer_name' => $invoice->contact?->full_name ?? 'مشتری',
                'customer_phone' => $invoice->contact?->phone_primary ?? '-',
                'items' => $invoice->items->map(fn ($i) => [
                    'name' => $i->name,
                    'quantity' => (float) $i->quantity,
                    'unit_price' => (float) $i->unit_price,
                    'discount_amount' => (float) $i->discount_amount,
                    'tax_amount' => (float) $i->tax_amount,
                    'total_amount' => (float) $i->total_amount,
                ])->all(),
                'subtotal' => (float) $invoice->subtotal,
                'discount_amount' => (float) $invoice->discount_amount,
                'tax_amount' => (float) $invoice->tax_amount,
                'total_amount' => (float) $invoice->total_amount,
                'paid_amount' => (float) $invoice->paid_amount,
                'remaining_amount' => (float) $invoice->remaining_amount,
                'qr_verification' => "VRO://INVOICE/{$invoice->uuid}",
            ],
        ];
    }

    /**
     * Generate a clean RTL Persian HTML document suitable for PDF/Print export.
     */
    public function generatePrintableHtml(Invoice $invoice): string
    {
        $payload = $this->getReceiptPrinterPayload($invoice)['receipt'];
        $rowsHtml = '';

        foreach ($payload['items'] as $idx => $item) {
            $num = $idx + 1;
            $price = number_format($item['unit_price']);
            $tot = number_format($item['total_amount']);
            $rowsHtml .= "<tr><td>{$num}</td><td>{$item['name']}</td><td>{$item['quantity']}</td><td>{$price}</td><td>{$tot}</td></tr>";
        }

        $totalFormatted = number_format($payload['total_amount']);

        return <<<HTML
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>فاکتور {$payload['invoice_number']}</title>
<style>
  body { font-family: 'IranYekan', Tahoma, sans-serif; direction: rtl; padding: 24px; color: #0f172a; }
  table { width: 100%; border-collapse: collapse; margin-top: 16px; }
  th, td { border: 1px solid #cbd5e1; padding: 8px 12px; text-align: right; font-size: 13px; }
  th { background: #f1f5f9; }
</style>
</head>
<body>
  <h2>{$payload['company_name']} — صورت‌حساب رسمی ({$payload['invoice_number']})</h2>
  <p>مشتری: <strong>{$payload['customer_name']}</strong> ({$payload['customer_phone']}) | تاریخ: {$payload['issue_date']}</p>
  <table>
    <thead>
      <tr><th>#</th><th>شرح کالا / خدمت</th><th>تعداد</th><th>فی (تومان)</th><th>مبلغ کل (تومان)</th></tr>
    </thead>
    <tbody>{$rowsHtml}</tbody>
  </table>
  <h3>مبلغ قابل پرداخت: {$totalFormatted} تومان</h3>
</body>
</html>
HTML;
    }
}
