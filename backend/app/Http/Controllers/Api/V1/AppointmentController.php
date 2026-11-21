<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffSchedule;
use App\Services\Appointments\AvailabilityEngine;
use App\Services\Appointments\ReminderScheduler;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AppointmentController extends Controller
{
    public function __construct(
        private readonly AvailabilityEngine $availabilityEngine,
        private readonly ReminderScheduler $reminderScheduler
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Appointment::class);

        $user = $request->user();
        $query = Appointment::with([
            'contact:id,first_name,last_name,phone_primary,is_vip',
            'service:id,name,duration_minutes,price,color',
            'staff:id,name,title',
            'branch:id,name',
        ]);

        if ($user->branch_id && ! $user->hasRole('owner')) {
            $query->where(function ($q) use ($user) {
                $q->where('branch_id', $user->branch_id)->orWhereNull('branch_id');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->integer('staff_id'));
        }

        if ($request->filled('contact_id')) {
            $query->where('contact_id', $request->integer('contact_id'));
        }

        if ($request->filled('date')) {
            $query->whereDate('starts_at', $request->input('date'));
        }

        $appointments = $query->orderBy('starts_at')->paginate($request->integer('per_page', 30));

        return response()->json($appointments);
    }

    public function availability(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Appointment::class);

        $validated = $request->validate([
            'staff_id' => ['required', 'exists:staff,id'],
            'service_id' => ['required', 'exists:services,id'],
            'date' => ['required', 'date'],
        ]);

        $staff = Staff::findOrFail($validated['staff_id']);
        $service = Service::findOrFail($validated['service_id']);
        $slots = $this->availabilityEngine->getAvailableSlots($staff, $service, $validated['date']);

        return response()->json([
            'date' => $validated['date'],
            'staff_id' => $staff->id,
            'service_id' => $service->id,
            'slots' => $slots,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Appointment::class);

        $validated = $request->validate([
            'contact_id' => ['required', 'exists:contacts,id'],
            'service_id' => ['required', 'exists:services,id'],
            'staff_id' => ['required', 'exists:staff,id'],
            'starts_at' => ['required', 'date'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'notes' => ['nullable', 'string'],
            'source' => ['nullable', 'string', 'in:call,online,manual,campaign'],
            'discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $staff = Staff::findOrFail($validated['staff_id']);
        $service = Service::findOrFail($validated['service_id']);
        $startsAt = Carbon::parse($validated['starts_at'], 'Asia/Tehran');

        $check = $this->availabilityEngine->checkSlot($staff, $service, $startsAt);
        if (! $check['available']) {
            return response()->json([
                'success' => false,
                'message' => $check['reason'],
            ], 422);
        }

        $duration = max(5, $service->duration_minutes ?: 30);
        $endsAt = $startsAt->copy()->addMinutes($duration);
        $price = (float) $service->price;
        $discount = (float) ($validated['discount'] ?? 0);
        $taxable = max(0, $price - $discount);
        $tax = round($taxable * 0.09, 2);
        $totalAmount = round($taxable + $tax, 2);

        $appointment = Appointment::create([
            'branch_id' => $validated['branch_id'] ?? $request->user()->branch_id,
            'contact_id' => $validated['contact_id'],
            'service_id' => $service->id,
            'staff_id' => $staff->id,
            'status' => 'confirmed',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'duration_minutes' => $duration,
            'price' => $price,
            'discount' => $discount,
            'tax' => $tax,
            'total_amount' => $totalAmount,
            'paid_amount' => 0,
            'payment_status' => 'unpaid',
            'source' => $validated['source'] ?? 'manual',
            'created_by' => $request->user()->id,
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->reminderScheduler->scheduleForAppointment($appointment);

        return response()->json([
            'success' => true,
            'message' => 'نوبت با موفقیت رزرو شد و یادآور پیامکی زمان‌بندی گردید.',
            'appointment' => $appointment->load(['contact', 'service', 'staff', 'reminders']),
        ], 201);
    }

    public function show(Appointment $appointment): JsonResponse
    {
        $this->authorize('view', $appointment);

        return response()->json([
            'appointment' => $appointment->load(['contact', 'service', 'staff', 'branch', 'reminders']),
        ]);
    }

    public function update(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('update', $appointment);

        $validated = $request->validate([
            'starts_at' => ['sometimes', 'date'],
            'status' => ['sometimes', 'string', 'in:pending,confirmed,completed,cancelled,no_show,rescheduled'],
            'notes' => ['nullable', 'string'],
        ]);

        if (! empty($validated['starts_at'])) {
            $newStartsAt = Carbon::parse($validated['starts_at'], 'Asia/Tehran');
            $staff = Staff::findOrFail($appointment->staff_id);
            $service = Service::findOrFail($appointment->service_id);

            $check = $this->availabilityEngine->checkSlot($staff, $service, $newStartsAt, $appointment->id);
            if (! $check['available']) {
                return response()->json([
                    'success' => false,
                    'message' => $check['reason'],
                ], 422);
            }

            $validated['starts_at'] = $newStartsAt;
            $validated['ends_at'] = $newStartsAt->copy()->addMinutes($appointment->duration_minutes);
            $validated['status'] = $validated['status'] ?? 'rescheduled';
        }

        $appointment->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'اطلاعات نوبت به‌روزرسانی شد.',
            'appointment' => $appointment->load(['contact', 'service', 'staff']),
        ]);
    }

    public function destroy(Appointment $appointment): JsonResponse
    {
        $this->authorize('delete', $appointment);

        $appointment->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
        $appointment->delete();

        return response()->json([
            'success' => true,
            'message' => 'نوبت لغو و حذف شد.',
        ]);
    }

    public function confirm(Appointment $appointment): JsonResponse
    {
        $this->authorize('update', $appointment);

        $appointment->update(['status' => 'confirmed']);

        return response()->json([
            'success' => true,
            'message' => 'نوبت تایید شد.',
            'appointment' => $appointment,
        ]);
    }

    public function complete(Appointment $appointment): JsonResponse
    {
        $this->authorize('update', $appointment);

        $appointment->update(['status' => 'completed']);

        return response()->json([
            'success' => true,
            'message' => 'نوبت با موفقیت انجام و تکمیل شد.',
            'appointment' => $appointment,
        ]);
    }

    public function noShow(Appointment $appointment): JsonResponse
    {
        $this->authorize('update', $appointment);

        $appointment->update(['status' => 'no_show']);

        return response()->json([
            'success' => true,
            'message' => 'وضعیت عدم حضور مراجع ثبت گردید.',
            'appointment' => $appointment,
        ]);
    }

    public function services(): JsonResponse
    {
        return response()->json([
            'services' => Service::where('is_active', true)->orderBy('id')->get(),
        ]);
    }

    public function storeService(Request $request): JsonResponse
    {
        $this->authorize('create', Appointment::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'buffer_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'price' => ['required', 'numeric', 'min:0'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $service = Service::create([
            'branch_id' => $request->user()->branch_id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'duration_minutes' => $validated['duration_minutes'],
            'buffer_minutes' => $validated['buffer_minutes'] ?? 10,
            'price' => $validated['price'],
            'color' => $validated['color'] ?? '#3B82F6',
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'خدمت جدید با موفقیت تعریف شد.',
            'service' => $service,
        ], 201);
    }

    public function staff(): JsonResponse
    {
        return response()->json([
            'staff' => Staff::with('schedules')->where('is_active', true)->orderBy('id')->get(),
        ]);
    }

    public function storeStaff(Request $request): JsonResponse
    {
        $this->authorize('create', Appointment::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email'],
        ]);

        $staff = Staff::create([
            'branch_id' => $request->user()->branch_id,
            'name' => $validated['name'],
            'title' => $validated['title'] ?? 'کارشناس / پزشک',
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'is_active' => true,
        ]);

        $days = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday'];
        foreach ($days as $day) {
            StaffSchedule::create([
                'staff_id' => $staff->id,
                'day_of_week' => $day,
                'start_time' => '09:00',
                'end_time' => '17:00',
                'is_working' => true,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'کارشناس جدید همراه با برنامه کاری هفتگی ثبت شد.',
            'staff' => $staff->load('schedules'),
        ], 201);
    }

    public function staffSchedule(Staff $staff): JsonResponse
    {
        return response()->json([
            'staff' => $staff,
            'schedules' => $staff->schedules,
            'breaks' => $staff->breaks,
        ]);
    }

    public function updateStaffSchedule(Request $request, Staff $staff): JsonResponse
    {
        $this->authorize('create', Appointment::class);

        $validated = $request->validate([
            'schedules' => ['required', 'array'],
            'schedules.*.day_of_week' => ['required', 'string'],
            'schedules.*.start_time' => ['required', 'string'],
            'schedules.*.end_time' => ['required', 'string'],
            'schedules.*.is_working' => ['required', 'boolean'],
        ]);

        foreach ($validated['schedules'] as $sched) {
            StaffSchedule::updateOrCreate(
                [
                    'staff_id' => $staff->id,
                    'day_of_week' => strtolower($sched['day_of_week']),
                ],
                [
                    'start_time' => $sched['start_time'],
                    'end_time' => $sched['end_time'],
                    'is_working' => $sched['is_working'],
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'برنامه کاری کارشناس ذخیره شد.',
            'schedules' => $staff->schedules()->get(),
        ]);
    }
}
