<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Call;
use App\Models\Campaign;
use App\Models\CampaignCallLog;
use App\Models\Contact;
use App\Models\ContactNote;
use App\Models\DoNotCall;
use App\Models\Invoice;
use App\Models\MessageLog;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ReportController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $branchId = $request->filled('branch_id') ? $request->integer('branch_id') : null;

        $callQuery = Call::query();
        $contactQuery = Contact::query();
        $appointmentQuery = Appointment::query();
        $invoiceQuery = Invoice::query();

        if ($branchId) {
            $callQuery->where('branch_id', $branchId);
            $contactQuery->where('branch_id', $branchId);
            $appointmentQuery->where('branch_id', $branchId);
            $invoiceQuery->where('branch_id', $branchId);
        }

        $totalCalls = (clone $callQuery)->count();
        $inboundCalls = (clone $callQuery)->where('direction', 'inbound')->count();
        $outboundCalls = (clone $callQuery)->where('direction', 'outbound')->count();
        $missedCalls = (clone $callQuery)->where('status', 'missed')->count();
        $avgCallDuration = (int) round((float) (clone $callQuery)->where('duration_seconds', '>', 0)->avg('duration_seconds'));

        $totalInvoiced = (float) (clone $invoiceQuery)->sum('total_amount');
        $totalCollected = (float) (clone $invoiceQuery)->sum('paid_amount');
        $totalOutstanding = (float) (clone $invoiceQuery)->sum('remaining_amount');

        return response()->json([
            'kpis' => [
                'total_contacts' => $contactQuery->count(),
                'vip_contacts' => (clone $contactQuery)->where('is_vip', true)->count(),
                'total_calls' => $totalCalls,
                'inbound_calls' => $inboundCalls,
                'outbound_calls' => $outboundCalls,
                'missed_calls' => $missedCalls,
                'avg_call_duration_seconds' => $avgCallDuration,
                'total_campaigns' => Campaign::count(),
                'dnc_count' => DoNotCall::count(),
                'total_appointments' => $appointmentQuery->count(),
                'confirmed_appointments' => (clone $appointmentQuery)->where('status', 'confirmed')->count(),
                'total_invoiced_toman' => $totalInvoiced,
                'total_collected_toman' => $totalCollected,
                'total_outstanding_toman' => $totalOutstanding,
                'total_sms_sent' => MessageLog::where('status', 'sent')->count(),
            ],
            'charts' => [
                'calls_by_direction' => [
                    ['name' => 'تماس ورودی', 'count' => $inboundCalls],
                    ['name' => 'تماس خروجی', 'count' => $outboundCalls],
                    ['name' => 'بی‌پاسخ', 'count' => $missedCalls],
                ],
                'revenue_breakdown' => [
                    ['name' => 'مبلغ صادرشده', 'amount' => $totalInvoiced],
                    ['name' => 'وصول‌شده', 'amount' => $totalCollected],
                    ['name' => 'مانده مطالبات', 'amount' => $totalOutstanding],
                ],
            ],
        ]);
    }

    public function calls(Request $request): JsonResponse
    {
        $query = Call::query();

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('started_at', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('started_at', '<=', $request->input('to_date'));
        }

        $calls = $query->get();

        $byStatus = [
            ['status' => 'ended', 'label' => 'پایان یافته', 'count' => $calls->where('status.value', 'ended')->count()],
            ['status' => 'active', 'label' => 'در حال مکالمه', 'count' => $calls->where('status.value', 'active')->count()],
            ['status' => 'missed', 'label' => 'بی‌پاسخ', 'count' => $calls->where('status.value', 'missed')->count()],
            ['status' => 'transferred', 'label' => 'منتقل شده', 'count' => $calls->where('status.value', 'transferred')->count()],
        ];

        return response()->json([
            'total_calls' => $calls->count(),
            'inbound_count' => $calls->where('direction.value', 'inbound')->count(),
            'outbound_count' => $calls->where('direction.value', 'outbound')->count(),
            'ivr_handled_count' => $calls->where('type.value', 'ivr')->count(),
            'avg_duration_seconds' => (int) round((float) $calls->avg('duration_seconds')),
            'avg_ring_seconds' => (int) round((float) $calls->avg('ring_duration')),
            'by_status' => $byStatus,
        ]);
    }

    public function campaigns(): JsonResponse
    {
        $campaigns = Campaign::all();
        $logs = CampaignCallLog::all();

        return response()->json([
            'total_campaigns' => $campaigns->count(),
            'total_targets' => (int) $campaigns->sum('total_contacts'),
            'total_completed' => (int) $campaigns->sum('completed_contacts'),
            'total_success' => (int) $campaigns->sum('success_count'),
            'total_failed' => (int) $campaigns->sum('failed_count'),
            'avg_listen_percentage' => (int) round((float) $logs->avg('listen_percentage')),
            'dnc_total' => DoNotCall::count(),
            'campaigns_chart' => $campaigns->map(fn ($c) => [
                'name' => $c->name,
                'total' => $c->total_contacts,
                'success' => $c->success_count,
                'failed' => $c->failed_count,
            ])->values(),
        ]);
    }

    public function appointments(): JsonResponse
    {
        $appointments = Appointment::with(['service:id,name', 'staff:id,name'])->get();

        $byStatus = [
            ['label' => 'تایید شده', 'count' => $appointments->where('status', 'confirmed')->count()],
            ['label' => 'انجام شده', 'count' => $appointments->where('status', 'completed')->count()],
            ['label' => 'عدم حضور', 'count' => $appointments->where('status', 'no_show')->count()],
            ['label' => 'لغو شده', 'count' => $appointments->where('status', 'cancelled')->count()],
        ];

        return response()->json([
            'total_appointments' => $appointments->count(),
            'total_value_toman' => (float) $appointments->sum('total_amount'),
            'by_status' => $byStatus,
        ]);
    }

    public function revenue(): JsonResponse
    {
        $invoices = Invoice::all();
        $payments = Payment::where('status', 'completed')->get();

        $byMethod = [
            ['method' => 'card', 'label' => 'کارتخوان / کارت', 'amount' => (float) $payments->where('method', 'card')->sum('amount')],
            ['method' => 'cash', 'label' => 'نقدی', 'amount' => (float) $payments->where('method', 'cash')->sum('amount')],
            ['method' => 'credit', 'label' => 'کیف پول مشتری', 'amount' => (float) $payments->where('method', 'credit')->sum('amount')],
            ['method' => 'gateway', 'label' => 'درگاه آنلاین', 'amount' => (float) $payments->where('method', 'gateway')->sum('amount')],
            ['method' => 'cheque', 'label' => 'چک بانکی', 'amount' => (float) $payments->where('method', 'cheque')->sum('amount')],
        ];

        return response()->json([
            'total_invoiced' => (float) $invoices->sum('total_amount'),
            'total_discount' => (float) $invoices->sum('discount_amount'),
            'total_tax' => (float) $invoices->sum('tax_amount'),
            'total_paid' => (float) $invoices->sum('paid_amount'),
            'total_remaining_debt' => (float) $invoices->sum('remaining_amount'),
            'by_payment_method' => $byMethod,
        ]);
    }

    public function users(): JsonResponse
    {
        $users = User::with(['branch:id,name', 'roles'])->get();

        $performance = $users->map(function (User $u) {
            $userCalls = Call::where('user_id', $u->id)->get();
            $notesCount = ContactNote::where('user_id', $u->id)->count();
            $invoicesCount = Invoice::where('created_by', $u->id)->count();

            return [
                'id' => $u->id,
                'name' => $u->name,
                'role' => $u->roles->first()?->name ?? 'operator',
                'branch' => $u->branch?->name ?? 'شعبه مرکزی',
                'calls_handled' => $userCalls->count(),
                'total_talk_seconds' => (int) $userCalls->sum('duration_seconds'),
                'notes_created' => $notesCount,
                'invoices_created' => $invoicesCount,
            ];
        });

        return response()->json([
            'users_performance' => $performance,
        ]);
    }

    public function export(Request $request): Response
    {
        $module = (string) $request->input('module', 'calls');

        $lines = [];
        if ($module === 'revenue') {
            $lines[] = 'Invoice Number,Customer ID,Total Amount,Paid Amount,Remaining,Status,Issue Date';
            foreach (Invoice::orderBy('id')->get() as $inv) {
                $lines[] = implode(',', [
                    $inv->invoice_number,
                    $inv->contact_id,
                    $inv->total_amount,
                    $inv->paid_amount,
                    $inv->remaining_amount,
                    $inv->status,
                    $inv->issue_date?->toDateString(),
                ]);
            }
        } elseif ($module === 'appointments') {
            $lines[] = 'Booking Number,Contact ID,Service ID,Staff ID,Status,Starts At,Total Amount';
            foreach (Appointment::orderBy('id')->get() as $apt) {
                $lines[] = implode(',', [
                    $apt->booking_number,
                    $apt->contact_id,
                    $apt->service_id,
                    $apt->staff_id,
                    $apt->status,
                    $apt->starts_at?->toDateTimeString(),
                    $apt->total_amount,
                ]);
            }
        } else {
            $lines[] = 'Call ID,Caller,Callee,Direction,Type,Status,Duration (s),Disposition,Started At';
            foreach (Call::orderBy('id')->get() as $c) {
                $lines[] = implode(',', [
                    $c->id,
                    $c->caller_number,
                    $c->callee_number,
                    $c->direction->value,
                    $c->type->value,
                    $c->status->value,
                    $c->duration_seconds,
                    str_replace(',', ' ', (string) $c->disposition),
                    $c->started_at?->toDateTimeString(),
                ]);
            }
        }

        $content = "\xEF\xBB\xBF".implode("\n", $lines);

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="voicereach-'.$module.'-report.csv"',
        ]);
    }
}
