# مستندات جامع API سامانه VoiceReach OS (نسخه ۱.۰)

سامانه **VoiceReach OS** دارای معماری تک‌سازمانی با پشتیبانی از چندشعبه‌ای (Single-Tenant, Multi-Branch) بوده و کلیه مسیرهای ارتباطی آن تحت پیشوند `/api/v1` ارائه می‌گردند.

---

## ۱. ساختار احراز هویت و امنیت

تمامی درخواست‌ها (به جز مسیرهای احراز هویت عمومی، استقرار اولیه و وبهوک‌های ورودی) نیازمند هدر Bearer Token در درخواست HTTP هستند:

```http
Authorization: Bearer <PERSONAL_ACCESS_TOKEN_OR_SANCTUM_TOKEN>
Accept: application/json
Content-Type: application/json
```

### هدرهای امنیتی وبهوک‌های خروجی (Outgoing Webhooks)
رویدادهای ارسال‌شده از سوی سامانه به مقصدهای بیرونی با الگوریتم **HMAC-SHA256** امضا می‌شوند:
- `X-VoiceReach-Event`: نوع رویداد (مثلاً `call.started` یا `invoice.paid`)
- `X-VoiceReach-Timestamp`: برچسب زمانی Unix (جهت جلوگیری از حملات Replay)
- `X-VoiceReach-Signature`: هش `hash_hmac('sha256', timestamp . '.' . payload, webhook_secret)`

---

## ۲. فهرست اندپوینت‌های کلیدی

### ۲.۱. نصب و استقرار سامانه (Installation Wizard)

#### بررسی وضعیت استقرار
- **متد:** `GET /api/v1/setup/status`
- **احراز هویت:** غیرضروری (عمومی)
- **پاسخ موفق (200):**
```json
{
  "status": "success",
  "data": {
    "is_installed": false,
    "has_company": false,
    "has_user": false,
    "database_ready": true,
    "php_version": "8.3.16",
    "app_name": "VoiceReach OS"
  }
}
```

#### نصب و راه‌اندازی اولیه
- **متد:** `POST /api/v1/setup/install`
- **احراز هویت:** غیرضروری (تنها یک‌بار قبل از نصب قابل اجراست)
- **بدنه درخواست:**
```json
{
  "company_name": "کلینیک تخصصی دکتر تهرانی",
  "company_phone": "02188889999",
  "company_address": "تهران، خیابان ولیعصر",
  "admin_name": "امید تهرانی",
  "admin_email": "admin@voicereach.local",
  "admin_phone": "09121112233",
  "admin_password": "SecurePassword123!",
  "branch_name": "شعبه مرکزی ولیعصر",
  "branch_code": "MAIN",
  "voip_adapter": "asterisk",
  "voip_host": "192.168.1.100",
  "voip_port": 5038
}
```
- **پاسخ موفق (201):**
```json
{
  "status": "success",
  "message": "سامانه VoiceReach OS با موفقیت نصب و پیکربندی شد.",
  "data": {
    "user": { "id": 1, "name": "امید تهرانی", "email": "admin@voicereach.local" },
    "token": "1|p3G..."
  }
}
```

---

### ۲.۲. احراز هویت کاربران (Authentication)

#### ورود با رمز عبور
- **متد:** `POST /api/v1/auth/login`
- **بدنه:**
```json
{
  "email": "admin@voicereach.local",
  "password": "SecurePassword123!"
}
```

#### ورود دو مرحله‌ای / پیامکی OTP
- درخواست کد: `POST /api/v1/auth/login/otp/request` (`{"phone": "09121112233"}`)
- تأیید کد: `POST /api/v1/auth/login/otp/verify` (`{"phone": "09121112233", "code": "123456"}`)

---

### ۲.۳. میز کار تلفنی و تماس‌ها (Telephony Core)

#### رویدادهای ورودی سانترال (VoIP Webhook)
- **متد:** `POST /api/v1/calls/webhook`
- **هدرها:**
  - `X-VoIP-Signature`: امضای HMAC-SHA256
  - `X-VoIP-Timestamp`: زمان درخواست (پنجره ۵ دقیقه مجاز)
  - `X-Idempotency-Key`: کلید یکتایی رویداد
- **بدنه نمونه:**
```json
{
  "event": "call.started",
  "call_id": "CALL-170001",
  "caller": "09123456789",
  "destination": "101",
  "direction": "inbound",
  "timestamp": 1700000000
}
```

#### پخش استریم صوت ضبط‌شده با امضای رمزی
- **متد:** `GET /api/v1/calls/{call}/recording/stream?signature=...&expires=...`
- دسترسی مستقیم به فایل محافظت شده در `storage/app/recordings`.

---

### ۲.۴. مدیریت مخاطبان CRM (Customer 360)

- `GET /api/v1/contacts`: فهرست مخاطبان با صفحه‌بندی و فیلتر
- `POST /api/v1/contacts`: ایجاد پرونده مشتری جدید
- `GET /api/v1/contacts/{id}`: مشخصات کامل مشتری، سوابق تماس‌ها، فاکتورها، و یادداشت‌ها
- `POST /api/v1/contacts/{id}/notes`: ثبت یادداشت جدید
- `POST /api/v1/contacts/import`: بارگذاری فایل اکسل/CSV مخاطبان با صف پس‌زمینه

---

### ۲.۵. موتور کمپین صوتی و فهرست DNC

- `GET /api/v1/campaigns`: فهرست کمپین‌های صوتی
- `POST /api/v1/campaigns`: ایجاد کمپین جدید با محدودیت ساعات تماس (۰۹:۰۰ الی ۲۱:۰۰ به وقت تهران)
- `POST /api/v1/campaigns/{id}/start`: شروع اجرای کمپین با فیلتر خودکار لیست DNC
- `POST /api/v1/campaigns/{id}/pause`: توقف موقت کمپین
- `GET /api/v1/dnc`: مشاهده و جستجوی شماره‌های Do Not Call
- `POST /api/v1/dnc`: افزودن شماره به لیست سیاه عدم تماس

---

### ۲.۶. نوبت‌دهی و رزرو زمان (Jalali Appointments)

- `GET /api/v1/appointments`: لیست نوبت‌های ثبت شده
- `POST /api/v1/appointments`: رزرو وقت جدید با کنترل تداخل، تعطیلات رسمی و زمان استراحت پرسنل
- `GET /api/v1/appointments/available-slots`: دریافت اسلات‌های زمانی خالی بر اساس تاریخ شمسی و خدمت

---

### ۲.۷. فاکتور، پرداخت و چاپ حرارتی (Billing & dqnamo Printer)

- `GET /api/v1/invoices`: لیست فاکتورها با وضعیت پرداخت
- `POST /api/v1/invoices`: صدور فاکتور با محاسبه ارزش افزوده (VAT) و تخفیف
- `POST /api/v1/invoices/{id}/payment`: ثبت دریافت نقدی، کارتخوان یا چک
- `GET /api/v1/invoices/{id}/receipt`: دریافت دیتای فرمت شده جهت انیمیشن و خروجی چاپگر فیش dqnamo

---

### ۲.۸. هوش مصنوعی و جریان تماس (AI Receptionist IVR)

- `GET /api/v1/ivr/flows`: دریافت جریان‌های تماس گراف‌محور
- `POST /api/v1/ivr/flows`: ذخیره و نشر گره‌های جریان تماس صوتی
- `POST /api/v1/ivr/simulate`: شبیه‌سازی متنی پردازش نیت کلامی فارسی (مانند رزرو، پشتیبانی، اتصال به اپراتور)

---

### ۲.۹. توسعه‌دهندگان، وب‌هوک‌ها و ممیزی (Phase 6 Developer API)

#### کلیدهای دسترسی API (Sanctum Tokens)
- `GET /api/v1/developer/tokens`: فهرست کلیدهای API فعال
- `POST /api/v1/developer/tokens`: تولید کلید جدید
  - بدنه: `{"name": "CRM Sync", "abilities": ["contacts:read", "calls:read"]}`
- `DELETE /api/v1/developer/tokens/{id}`: ابطال کلید دسترسی

#### مدیریت وب‌هوک‌ها (Webhooks)
- `GET /api/v1/developer/webhooks`: لیست وب‌هوک‌های ثبت‌شده
- `POST /api/v1/developer/webhooks`: ثبت وب‌هوک جدید
  - بدنه:
    ```json
    {
      "name": "حسابداری سپیدار",
      "url": "https://example.com/api/webhooks",
      "secret": "my_shared_secret",
      "events": ["invoice.paid", "appointment.created"]
    }
    ```
- `POST /api/v1/developer/webhooks/{id}/test`: ارسال پیام آزمایشی (Ping)
- `GET /api/v1/developer/webhooks/{id}/deliveries`: مشاهده سوابق و وضعیت پاسخ‌های HTTP گیرنده

#### لاگ‌های ممیزی و امنیت
- `GET /api/v1/developer/audit-logs`: فیلتر بر اساس `event_type`، `user_id` و تاریخ
- `GET /api/v1/developer/system-status`: گزارش سلامت فنی سرور و سرویس‌ها
