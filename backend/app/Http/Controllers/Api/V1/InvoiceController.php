<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AccountingLog;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Billing\InvoiceService;
use App\Services\Billing\PaymentService;
use App\Services\Messaging\SmsManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly PaymentService $paymentService,
        private readonly SmsManager $smsManager
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $user = $request->user();
        $query = Invoice::with([
            'contact:id,first_name,last_name,phone_primary,credit_balance,total_debt',
            'branch:id,name',
            'items',
            'payments',
        ]);

        if ($user->branch_id && ! $user->hasRole('owner')) {
            $query->where(function ($q) use ($user) {
                $q->where('branch_id', $user->branch_id)->orWhereNull('branch_id');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('contact_id')) {
            $query->where('contact_id', $request->integer('contact_id'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('contact', function ($cq) use ($search) {
                        $cq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('phone_primary', 'like', "%{$search}%");
                    });
            });
        }

        $invoices = $query->latest()->paginate($request->integer('per_page', 20));

        return response()->json($invoices);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Invoice::class);

        $validated = $request->validate([
            'contact_id' => ['required', 'exists:contacts,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'type' => ['nullable', 'string', 'in:service,product,campaign,subscription,custom'],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'appointment_id' => ['nullable', 'exists:appointments,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.service_id' => ['nullable', 'exists:services,id'],
        ]);

        $validated['branch_id'] = $validated['branch_id'] ?? $request->user()->branch_id;

        $invoice = $this->invoiceService->createInvoice($validated, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => "فاکتور شماره {$invoice->invoice_number} با موفقیت صادر و در دفتر روزنامه ثبت شد.",
            'invoice' => $invoice,
        ], 201);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        return response()->json([
            'invoice' => $invoice->load(['contact', 'branch', 'items', 'payments.cheque', 'creator:id,name']),
        ]);
    }

    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('update', $invoice);

        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'in:draft,sent,partial,paid,overdue,cancelled,refunded'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $invoice->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'فاکتور به‌روزرسانی شد.',
            'invoice' => $invoice->load(['contact', 'items', 'payments']),
        ]);
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $this->authorize('delete', $invoice);

        $invoice->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
        $invoice->delete();

        return response()->json([
            'success' => true,
            'message' => 'فاکتور لغو و بایگانی شد.',
        ]);
    }

    public function send(Invoice $invoice): JsonResponse
    {
        $this->authorize('update', $invoice);

        $invoice->loadMissing('contact');
        $contact = $invoice->contact;

        if ($contact && ! empty($contact->phone_primary)) {
            $amountFormatted = number_format((float) $invoice->total_amount);
            $body = "{$contact->full_name} گرامی، فاکتور شماره {$invoice->invoice_number} به مبلغ {$amountFormatted} تومان صادر شد.";

            $this->smsManager->sendSms(
                $contact->phone_primary,
                $body,
                $contact->id,
                null,
                ['reference_type' => 'invoice', 'reference_id' => $invoice->id]
            );
        }

        $invoice->update([
            'status' => $invoice->status === 'draft' ? 'sent' : $invoice->status,
            'sent_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'پیامک اطلاع‌رسانی فاکتور برای مشتری ارسال گردید.',
            'invoice' => $invoice,
        ]);
    }

    public function recordPayment(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('update', $invoice);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'method' => ['required', 'string', 'in:cash,card,transfer,cheque,credit,gateway'],
            'gateway_name' => ['nullable', 'string', 'max:50'],
            'gateway_ref' => ['nullable', 'string', 'max:100'],
            'cheque_number' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $payment = $this->paymentService->recordPayment($invoice, $validated, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'پرداخت با موفقیت ثبت و از مانده بدهی کسر شد.',
            'payment' => $payment,
            'invoice' => $invoice->fresh(['contact', 'items', 'payments']),
        ], 201);
    }

    public function receipt(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        return response()->json($this->invoiceService->getReceiptPrinterPayload($invoice));
    }

    public function pdf(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        $html = $this->invoiceService->generatePrintableHtml($invoice);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    public function paymentLink(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        $gateway = (string) $request->input('gateway', 'zarinpal');
        $link = $this->paymentService->createGatewayPaymentLink($invoice, $gateway);

        return response()->json([
            'success' => true,
            'message' => 'لینک پرداخت آنلاین درگاه زرین‌پال / آیدی‌پی ایجاد شد.',
            'payment_link' => $link,
        ]);
    }

    public function payments(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $payments = Payment::with(['invoice:id,invoice_number,total_amount', 'contact:id,first_name,last_name,phone_primary'])
            ->latest('paid_at')
            ->paginate($request->integer('per_page', 30));

        return response()->json($payments);
    }

    public function accountingLogs(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $query = AccountingLog::with(['contact:id,first_name,last_name,phone_primary', 'creator:id,name']);

        if ($request->filled('contact_id')) {
            $query->where('contact_id', $request->integer('contact_id'));
        }

        $logs = $query->latest('created_at')->paginate($request->integer('per_page', 30));

        return response()->json($logs);
    }

    public function topupWallet(Request $request, Contact $contact): JsonResponse
    {
        $this->authorize('create', Invoice::class);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1000'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $amount = round((float) $validated['amount'], 2);
        $newBalance = round(((float) $contact->credit_balance) + $amount, 2);
        $contact->update(['credit_balance' => $newBalance]);

        AccountingLog::create([
            'branch_id' => $contact->branch_id,
            'reference_type' => 'payment',
            'reference_id' => $contact->id,
            'contact_id' => $contact->id,
            'debit' => 0,
            'credit' => $amount,
            'balance_after' => (float) $contact->total_debt,
            'description' => $validated['description'] ?? 'افزایش اعتبار کیف پول مشتری',
            'created_by' => $request->user()->id,
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'کیف پول مشتری با موفقیت شارژ شد.',
            'contact' => $contact->fresh(),
        ]);
    }
}
