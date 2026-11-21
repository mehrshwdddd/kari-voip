<?php

namespace Database\Seeders;

use App\Enums\CallDirectionEnum;
use App\Enums\CallStatusEnum;
use App\Enums\CallTypeEnum;
use App\Enums\ContactSourceEnum;
use App\Enums\ContactStatusEnum;
use App\Enums\RoleEnum;
use App\Enums\UserStatusEnum;
use App\Models\AccountingLog;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Call;
use App\Models\CallDisposition;
use App\Models\CallQueue;
use App\Models\Campaign;
use App\Models\CampaignCallLog;
use App\Models\CampaignContact;
use App\Models\CampaignWorkflow;
use App\Models\CompanyProfile;
use App\Models\Contact;
use App\Models\ContactTag;
use App\Models\DoNotCall;
use App\Models\Integration;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\IvrFlow;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Models\Payment;
use App\Models\Service;
use App\Models\SmsProvider;
use App\Models\Staff;
use App\Models\StaffSchedule;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class InitialSystemSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Company Profile
        CompanyProfile::firstOrCreate(['id' => 1], [
            'name' => 'سامانه مدیریت ارتباط صوتی ویس‌ریچ',
            'phone' => '02188889999',
            'email' => 'info@voicereach.local',
            'website' => 'https://voicereach.local',
            'address' => 'تهران، خیابان ولیعصر، برج فناوری، طبقه ۴',
            'timezone' => 'Asia/Tehran',
            'working_hours' => [
                'saturday' => ['open' => '08:00', 'close' => '17:00'],
                'sunday' => ['open' => '08:00', 'close' => '17:00'],
                'monday' => ['open' => '08:00', 'close' => '17:00'],
                'tuesday' => ['open' => '08:00', 'close' => '17:00'],
                'wednesday' => ['open' => '08:00', 'close' => '17:00'],
                'thursday' => ['open' => '08:00', 'close' => '13:00'],
                'friday' => ['closed' => true],
            ],
            'invoice_prefix' => 'VR-',
            'invoice_counter' => 1001,
        ]);

        // 2. Central Branch
        $branch = Branch::firstOrCreate(['code' => 'CENTRAL'], [
            'name' => 'شعبه مرکزی',
            'code' => 'CENTRAL',
            'phone' => '02188889999',
            'address' => 'تهران، میدان ولیعصر',
            'is_active' => true,
        ]);

        // 3. Admin / Owner User
        $owner = User::firstOrCreate(['email' => 'admin@voicereach.local'], [
            'branch_id' => $branch->id,
            'name' => 'مدیر سیستم',
            'email' => 'admin@voicereach.local',
            'phone' => '09120000000',
            'password' => Hash::make('password'),
            'status' => UserStatusEnum::ACTIVE,
        ]);

        $owner->assignRole(RoleEnum::OWNER->value);
        $branch->update(['manager_id' => $owner->id]);

        // 4. Sample Tags
        $tags = [
            ['name' => 'مشتری وفادار', 'color' => '#10B981'],
            ['name' => 'پیگیری فوری', 'color' => '#EF4444'],
            ['name' => 'پتانسیل خرید بالا', 'color' => '#F59E0B'],
            ['name' => 'سازمانی / شرکتی', 'color' => '#6366F1'],
        ];

        foreach ($tags as $tagData) {
            ContactTag::firstOrCreate(['name' => $tagData['name']], $tagData);
        }

        // 5. Sample Contact
        $contact = Contact::firstOrCreate(['phone_primary' => '09121112233'], [
            'branch_id' => $branch->id,
            'first_name' => 'سهراب',
            'last_name' => 'سپهری',
            'national_id' => '0012345678',
            'phone_primary' => '09121112233',
            'email' => 'sohrab@example.com',
            'status' => ContactStatusEnum::VIP,
            'source' => ContactSourceEnum::CALL,
            'assigned_user_id' => $owner->id,
            'credit_balance' => 5000000,
            'loyalty_points' => 120,
            'total_debt' => 0,
            'is_vip' => true,
            'notes' => 'مشتری قدیمی و بسیار محترم، ترجیح می‌دهد تماس‌ها قبل از ساعت ۱۲ باشد.',
            'created_by' => $owner->id,
        ]);

        $vipTag = ContactTag::where('name', 'مشتری وفادار')->first();
        if ($vipTag) {
            $contact->tagPivots()->syncWithoutDetaching([$vipTag->id]);
        }

        // 6. Default Call Dispositions
        $dispositions = [
            ['name' => 'پاسخ داده شد - مشاوره موفق', 'color' => '#10B981', 'requires_note' => false],
            ['name' => 'ثبت نوبت / سفارش جدید', 'color' => '#3B82F6', 'requires_note' => false],
            ['name' => 'نیاز به پیگیری مجدد', 'color' => '#F59E0B', 'requires_note' => true],
            ['name' => 'عدم پاسخگویی / اشغال', 'color' => '#64748B', 'requires_note' => false],
            ['name' => 'انتقال به واحد فروش / فنی', 'color' => '#6366F1', 'requires_note' => false],
            ['name' => 'شماره اشتباه / مزاحم', 'color' => '#EF4444', 'requires_note' => false],
        ];

        foreach ($dispositions as $disp) {
            CallDisposition::firstOrCreate(['name' => $disp['name']], $disp);
        }

        // 7. Default Call Queue
        $queue = CallQueue::firstOrCreate(['name' => 'صف پذیرش مرکزی'], [
            'branch_id' => $branch->id,
            'name' => 'صف پذیرش مرکزی',
            'strategy' => 'ringall',
            'max_wait_seconds' => 120,
            'agents' => [['id' => $owner->id, 'extension' => '101', 'name' => $owner->name]],
            'status' => 'active',
        ]);

        // 8. Default VoIP Integration
        Integration::firstOrCreate(['type' => 'voip'], [
            'name' => 'Asterisk PBX مرکزی',
            'type' => 'voip',
            'adapter_class' => 'asterisk',
            'credentials' => ['ari_user' => 'asterisk', 'ari_secret' => 'voicereach_ari'],
            'config' => [
                'host' => '127.0.0.1',
                'port' => 8088,
                'context' => 'from-internal',
                'reception_mode' => 'human_only',
            ],
            'status' => 'active',
            'last_tested_at' => now(),
        ]);

        // 9. Sample Historical Call Log
        Call::firstOrCreate(['voip_call_id' => 'seed_call_001'], [
            'branch_id' => $branch->id,
            'contact_id' => $contact->id,
            'caller_number' => '09121112233',
            'callee_number' => '02188889999',
            'direction' => CallDirectionEnum::INBOUND,
            'type' => CallTypeEnum::MANUAL,
            'status' => CallStatusEnum::ENDED,
            'user_id' => $owner->id,
            'queue_id' => $queue->id,
            'voip_provider' => 'asterisk',
            'voip_call_id' => 'seed_call_001',
            'voip_channel' => 'PJSIP/09121112233-001',
            'started_at' => now()->subHours(2),
            'answered_at' => now()->subHours(2)->addSeconds(4),
            'ended_at' => now()->subHours(2)->addSeconds(149),
            'ring_duration' => 4,
            'duration_seconds' => 145,
            'recording_path' => 'recordings/seed_call_001.wav',
            'disposition' => 'پاسخ داده شد - مشاوره موفق',
            'disposition_note' => 'مشتری جهت استعلام شرایط جدید تماس گرفت و راهنمایی کامل انجام شد.',
        ]);

        // 10. Sample Do Not Call (DNC) entry
        DoNotCall::firstOrCreate(['phone_number' => '09129990000'], [
            'phone_number' => '09129990000',
            'reason' => 'درخواست لغو دریافت پیام صوتی تبلیغاتی (کلید ۹)',
            'source' => 'dtmf_opt_out',
            'added_by' => $owner->id,
            'added_at' => now()->subDays(3),
        ]);

        // 11. Sample Voice Campaign
        $campaign = Campaign::firstOrCreate(['name' => 'کمپین اطلاع‌رسانی جشنواره پاییزی'], [
            'branch_id' => $branch->id,
            'name' => 'کمپین اطلاع‌رسانی جشنواره پاییزی',
            'description' => 'تماس صوتی خودکار با مشتریان ویژه جهت معرفی تخفیف ۳۰ درصدی خدمات و اتصال کلید ۱ به کارشناس فروش',
            'type' => 'audio',
            'audio_path' => 'campaigns/autumn_festival.wav',
            'status' => 'completed',
            'allowed_hours_start' => '09:00',
            'allowed_hours_end' => '20:00',
            'allowed_days' => ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday'],
            'max_concurrent_calls' => 5,
            'max_retry_count' => 2,
            'retry_interval_minutes' => [15, 60],
            'dtmf_options' => ['1' => 'transfer_agent', '9' => 'opt_out'],
            'human_transfer_enabled' => true,
            'record_calls' => true,
            'caller_id' => '02188889999',
            'total_contacts' => 1,
            'completed_contacts' => 1,
            'success_count' => 1,
            'failed_count' => 0,
            'created_by' => $owner->id,
        ]);

        $campContact = CampaignContact::firstOrCreate([
            'campaign_id' => $campaign->id,
            'phone_number' => $contact->phone_primary,
        ], [
            'contact_id' => $contact->id,
            'status' => 'completed',
            'attempt_count' => 1,
            'last_attempt_at' => now()->subHour(),
            'final_disposition' => 'human_transfer_requested',
        ]);

        CampaignCallLog::firstOrCreate([
            'campaign_id' => $campaign->id,
            'campaign_contact_id' => $campContact->id,
            'attempt_number' => 1,
        ], [
            'contact_id' => $contact->id,
            'phone_number' => $contact->phone_primary,
            'voip_call_id' => 'camp_seed_001',
            'started_at' => now()->subHour(),
            'answered_at' => now()->subHour()->addSeconds(3),
            'ended_at' => now()->subHour()->addSeconds(35),
            'duration_seconds' => 32,
            'listen_duration_seconds' => 30,
            'listen_percentage' => 95,
            'dtmf_input' => '1',
            'outcome' => 'human_transfer_requested',
            'agent_transfer_status' => 'transferred_to_queue',
            'retry_count' => 0,
            'created_at' => now()->subHour(),
        ]);

        // 12. Phase 4: Services & Staff
        $service1 = Service::firstOrCreate(['name' => 'مشاوره تخصصی و ویزیت VIP'], [
            'branch_id' => $branch->id,
            'name' => 'مشاوره تخصصی و ویزیت VIP',
            'description' => 'جلسه مشاوره اختصاصی ۴۵ دقیقه‌ای همراه با بررسی کامل پرونده',
            'duration_minutes' => 45,
            'buffer_minutes' => 15,
            'price' => 850000,
            'color' => '#2563EB',
            'is_active' => true,
        ]);

        Service::firstOrCreate(['name' => 'جلسه ارزیابی و خدمات پایه'], [
            'branch_id' => $branch->id,
            'name' => 'جلسه ارزیابی و خدمات پایه',
            'description' => 'ارزیابی اولیه ۳۰ دقیقه‌ای',
            'duration_minutes' => 30,
            'buffer_minutes' => 10,
            'price' => 450000,
            'color' => '#10B981',
            'is_active' => true,
        ]);

        $staff = Staff::firstOrCreate(['name' => 'دکتر علیرضا نادری'], [
            'branch_id' => $branch->id,
            'user_id' => $owner->id,
            'name' => 'دکتر علیرضا نادری',
            'title' => 'مشاور و متخصص ارشد',
            'phone' => '09121002000',
            'email' => 'naderi@voicereach.local',
            'is_active' => true,
        ]);

        foreach (['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday'] as $day) {
            StaffSchedule::firstOrCreate([
                'staff_id' => $staff->id,
                'day_of_week' => $day,
            ], [
                'start_time' => '09:00',
                'end_time' => '17:00',
                'is_working' => true,
            ]);
        }

        // 13. Sample Appointment
        $appointment = Appointment::firstOrCreate(['booking_number' => 'APT-1405-001'], [
            'branch_id' => $branch->id,
            'contact_id' => $contact->id,
            'service_id' => $service1->id,
            'staff_id' => $staff->id,
            'booking_number' => 'APT-1405-001',
            'status' => 'confirmed',
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(10, 45),
            'duration_minutes' => 45,
            'price' => 850000,
            'discount' => 50000,
            'tax' => 72000,
            'total_amount' => 872000,
            'paid_amount' => 872000,
            'payment_status' => 'paid',
            'source' => 'call',
            'created_by' => $owner->id,
            'notes' => 'رزرو تلفنی توسط منشی برای جلسه مشاوره تخصصی',
        ]);

        // 14. Sample Invoice, Payment & Accounting Logs
        $invoice = Invoice::firstOrCreate(['invoice_number' => 'VR-1001'], [
            'branch_id' => $branch->id,
            'invoice_number' => 'VR-1001',
            'contact_id' => $contact->id,
            'status' => 'paid',
            'type' => 'service',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 850000,
            'discount_amount' => 50000,
            'tax_amount' => 72000,
            'total_amount' => 872000,
            'paid_amount' => 872000,
            'remaining_amount' => 0,
            'currency' => 'IRR',
            'appointment_id' => $appointment->id,
            'created_by' => $owner->id,
            'sent_at' => now()->subMinutes(30),
            'paid_at' => now()->subMinutes(15),
            'notes' => 'تسویه کامل از طریق کارتخوان شعبه مرکزی',
        ]);

        InvoiceItem::firstOrCreate([
            'invoice_id' => $invoice->id,
            'name' => $service1->name,
        ], [
            'description' => 'نوبت شماره APT-1405-001',
            'quantity' => 1,
            'unit_price' => 850000,
            'discount_percent' => 5.88,
            'discount_amount' => 50000,
            'tax_percent' => 9,
            'tax_amount' => 72000,
            'total_amount' => 872000,
            'service_id' => $service1->id,
            'created_at' => now(),
        ]);

        Payment::firstOrCreate([
            'invoice_id' => $invoice->id,
            'gateway_ref' => 'REF-SEED-1001',
        ], [
            'contact_id' => $contact->id,
            'amount' => 872000,
            'method' => 'card',
            'gateway_name' => 'zarinpal',
            'status' => 'completed',
            'paid_at' => now()->subMinutes(15),
            'processed_by' => $owner->id,
        ]);

        AccountingLog::firstOrCreate([
            'reference_type' => 'invoice',
            'reference_id' => $invoice->id,
        ], [
            'branch_id' => $branch->id,
            'contact_id' => $contact->id,
            'debit' => 872000,
            'credit' => 0,
            'balance_after' => 872000,
            'description' => 'صدور فاکتور شماره VR-1001',
            'created_by' => $owner->id,
            'created_at' => now()->subMinutes(30),
        ]);

        AccountingLog::firstOrCreate([
            'reference_type' => 'payment',
            'reference_id' => $invoice->id,
        ], [
            'branch_id' => $branch->id,
            'contact_id' => $contact->id,
            'debit' => 0,
            'credit' => 872000,
            'balance_after' => 0,
            'description' => 'تسویه فاکتور شماره VR-1001',
            'created_by' => $owner->id,
            'created_at' => now()->subMinutes(15),
        ]);

        // 15. SMS Providers & Templates
        SmsProvider::firstOrCreate(['adapter_class' => 'kavenegar'], [
            'name' => 'کاوه‌نگار (Kavenegar)',
            'adapter_class' => 'kavenegar',
            'credentials' => ['api_key' => 'local_simulated'],
            'sender_number' => '10008663',
            'is_active' => true,
            'priority' => 1,
            'created_at' => now(),
        ]);

        SmsProvider::firstOrCreate(['adapter_class' => 'ghasedak'], [
            'name' => 'قاصدک (Ghasedak)',
            'adapter_class' => 'ghasedak',
            'credentials' => ['api_key' => 'local_simulated'],
            'sender_number' => '30005088',
            'is_active' => true,
            'priority' => 2,
            'created_at' => now(),
        ]);

        $tpl1 = MessageTemplate::firstOrCreate(['name' => 'تایید رزرو نوبت'], [
            'name' => 'تایید رزرو نوبت',
            'channel' => 'sms',
            'category' => 'appointment',
            'body' => '{customer_name} عزیز، نوبت {service_name} شما برای تاریخ {appointment_date} ساعت {appointment_time} ثبت شد. کد پیگیری: {booking_number}',
            'variables' => ['customer_name', 'service_name', 'appointment_date', 'appointment_time', 'booking_number'],
            'is_active' => true,
        ]);

        MessageTemplate::firstOrCreate(['name' => 'صدور صورت‌حساب و لینک پرداخت'], [
            'name' => 'صدور صورت‌حساب و لینک پرداخت',
            'channel' => 'sms',
            'category' => 'invoice',
            'body' => 'مشتری گرامی {customer_name}، صورت‌حساب شماره {invoice_number} به مبلغ {total_amount} تومان صادر گردید.',
            'variables' => ['customer_name', 'invoice_number', 'total_amount'],
            'is_active' => true,
        ]);

        MessageLog::firstOrCreate([
            'to_address' => $contact->phone_primary,
            'provider_ref' => 'kvn_seed_001',
        ], [
            'contact_id' => $contact->id,
            'channel' => 'sms',
            'from_address' => '10008663',
            'template_id' => $tpl1->id,
            'body' => 'سهراب سپهری عزیز، نوبت مشاوره تخصصی و ویزیت VIP شما برای فردا ساعت ۱۰:۰۰ ثبت شد. کد پیگیری: APT-1405-001',
            'status' => 'sent',
            'provider' => 'kavenegar',
            'sent_at' => now()->subMinutes(20),
            'reference_type' => 'appointment',
            'reference_id' => $appointment->id,
        ]);

        // 16. Phase 5: Smart AI Receptionist Flow & Campaign Workflow
        IvrFlow::firstOrCreate(['name' => 'جریان هوشمند پذیرش و نوبت‌دهی فارسی'], [
            'name' => 'جریان هوشمند پذیرش و نوبت‌دهی فارسی',
            'description' => 'پاسخگویی خودکار با تشخیص گفتار فارسی (STT)، رزرو وقت، استعلام صورت‌حساب و انتقال هوشمند به منشی انسانی',
            'nodes' => [
                [
                    'id' => 'node_greeting',
                    'type' => 'greeting',
                    'position' => ['x' => 380, 'y' => 40],
                    'data' => [
                        'title' => 'خوش‌آمدگویی صوتی فارسی (TTS)',
                        'prompt' => 'سلام، به سامانه ویس‌ریچ خوش آمدید. درخواست خود را به فارسی بیان کنید یا کلید ۱ را برای رزرو وقت بفشارید.',
                    ],
                ],
                [
                    'id' => 'node_intent_router',
                    'type' => 'intent_router',
                    'position' => ['x' => 380, 'y' => 170],
                    'data' => [
                        'title' => 'موتور تشخیص نیت گفتار فارسی (AI Intent Router)',
                        'intents' => ['book_appointment', 'billing_inquiry', 'support_ticket', 'human_transfer'],
                    ],
                ],
                [
                    'id' => 'node_book_appointment',
                    'type' => 'book_appointment',
                    'position' => ['x' => 80, 'y' => 320],
                    'data' => [
                        'title' => 'رزرو خودکار نوبت + پیامک تایید',
                        'intent' => 'book_appointment',
                    ],
                ],
                [
                    'id' => 'node_billing_inquiry',
                    'type' => 'billing_inquiry',
                    'position' => ['x' => 380, 'y' => 320],
                    'data' => [
                        'title' => 'استعلام بدهی و کیف پول مشتری',
                        'intent' => 'billing_inquiry',
                    ],
                ],
                [
                    'id' => 'node_human_transfer',
                    'type' => 'human_transfer',
                    'position' => ['x' => 680, 'y' => 320],
                    'data' => [
                        'title' => 'انتقال به صف منشی انسانی (داخلی ۱۰۱)',
                        'intent' => 'human_transfer',
                    ],
                ],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'node_greeting', 'target' => 'node_intent_router', 'label' => 'دریافت گفتار / DTMF'],
                ['id' => 'e2', 'source' => 'node_intent_router', 'target' => 'node_book_appointment', 'label' => 'نیت: رزرو نوبت (کلید ۱)'],
                ['id' => 'e3', 'source' => 'node_intent_router', 'target' => 'node_billing_inquiry', 'label' => 'نیت: امور مالی (کلید ۲)'],
                ['id' => 'e4', 'source' => 'node_intent_router', 'target' => 'node_human_transfer', 'label' => 'نیت: اتصال به اپراتور (کلید ۰)'],
            ],
            'is_active' => true,
            'published_at' => now(),
        ]);

        CampaignWorkflow::firstOrCreate(['name' => 'گردش کار کمپین فروش تعاملی'], [
            'campaign_id' => $campaign->id,
            'name' => 'گردش کار کمپین فروش تعاملی',
            'nodes' => [
                ['id' => 'cw_1', 'type' => 'dial', 'position' => ['x' => 350, 'y' => 40], 'data' => ['title' => 'شماره‌گیری خودکار (با فیلتر DNC)']],
                ['id' => 'cw_2', 'type' => 'play_audio', 'position' => ['x' => 350, 'y' => 160], 'data' => ['title' => 'پخش پیام صوتی جشنواره']],
                ['id' => 'cw_3', 'type' => 'dtmf_branch', 'position' => ['x' => 350, 'y' => 280], 'data' => ['title' => 'کلید ۱: انتقال به کارشناس | کلید ۹: ثبت در DNC']],
            ],
            'edges' => [
                ['id' => 'ce_1', 'source' => 'cw_1', 'target' => 'cw_2', 'label' => 'پاسخ داده شد'],
                ['id' => 'ce_2', 'source' => 'cw_2', 'target' => 'cw_3', 'label' => 'انتخاب کلید'],
            ],
            'is_template' => true,
        ]);
    }
}
