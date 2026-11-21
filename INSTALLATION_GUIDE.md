# راهنمای استقرار و نصب سامانه VoiceReach OS

این مستند نحوه استقرار، راه‌اندازی و نگهداری سامانه **VoiceReach OS** را بر روی سرورهای محلی، داخل سازمانی (On-Premise) و سرورهای ابری ایران توضیح می‌دهد.

---

## ۱. نیازمندی‌های سیستمی

- **سیستم‌عامل:** لینوکس (Ubuntu 22.04 LTS / Debian 12 / Rocky Linux 9) یا ویندوز سرور
- **نسخه PHP:** PHP 8.3 به همراه اکستنشن‌های `pdo`, `pdo_sqlite` یا `pdo_mysql`, `curl`, `mbstring`, `xml`, `zip`, `bcmath`, `fileinfo`
- **پایگاه‌داده:** SQLite (پیش‌فرض برای SMB) یا MySQL 8.0 / MariaDB 10.11
- **حافظه و صف:** Redis 7.x (جهت Horizon و صفوف تماس‌ها)
- **محیط کلاینت:** Node.js 20+ و مدیر بسته npm
- **ارتباط وب‌سوکت:** Laravel Reverb (پورت ۸۰۸۰)

---

## ۲. روش اول: نصب سریع با ویزارد تحت وب (Web Wizard)

برای کسب‌وکارهایی که ترجیح می‌دهند بدون استفاده از خط فرمان سامانه را بالا بیاورند:

1. فایل‌های پروژه را دانلود و وب‌سرور (Nginx یا Apache) را روی پوشه `backend/public` و `frontend/dist` تنظیم کنید.
2. مرورگر خود را باز کرده و به نشانی زیر بروید:
   ```
   http://your-server-ip/setup
   ```
3. مراحل پنج‌گانه ویزارد را تکمیل نمایید:
   - **گام ۱:** مشخصات سازمان و شماره تماس سازمانی
   - **گام ۲:** تعریف شعبه مرکزی و کد شعبه
   - **گام ۳:** ثبت اطلاعات حساب مدیر کل (Owner) و تعیین رمز عبور امن
   - **گام ۴:** انتخاب نوع سانترال تلفنی (Asterisk AMI یا Issabel)
   - **گام ۵:** بازبینی و کلیک روی **تکمیل و راه‌اندازی**
4. سیستم به طور خودکار پایگاه‌داده را آماده کرده و شما را مستقیم به **میز کار منشی تلفنی** هدایت می‌کند.

---

## ۳. روش دوم: نصب خودکار از طریق خط فرمان (CLI)

در صورتی که می‌خواهید سامانه را به صورت خودکار یا در محیط بدون واسط کاربری (Headless) نصب کنید:

```bash
# رفتن به دایرکتوری بک‌اند
cd backend

# نصب وابستگی‌های PHP
composer install --no-dev --optimize-autoloader

# کپی فایل محیط
cp .env.example .env

# اجرای دستور جامع نصب VoiceReach OS
php artisan voicereachos:install --force \
  --admin-name="مدیر سیستم" \
  --admin-email="admin@voicereach.local" \
  --admin-phone="09120000000" \
  --admin-password="StrongAdminPassword123!"
```

دستور فوق به ترتیب کارهای زیر را انجام می‌دهد:
- تولید کلید اپلیکیشن (`APP_KEY`)
- اجرای مایگریشن‌های پایگاه‌داده (`migrate --force`)
- ثبت نقش‌ها و دسترسی‌های سیستمی Spatie
- ایجاد لینک نمادین ذخیره‌سازی فایل‌های صوتی (`storage:link`)
- ساخت حساب سوپرادمین و شعبه مرکزی

---

## ۴. استقرار پروداکشن با داکر (Docker Compose)

یک فایل نمونه `docker-compose.yml` در ریشه پروژه قرار دهید:

```yaml
version: '3.8'

services:
  app:
    image: php:8.3-fpm
    restart: always
    volumes:
      - ./backend:/var/www/html
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
      - DB_CONNECTION=sqlite
      - QUEUE_CONNECTION=redis
      - REDIS_HOST=redis
    depends_on:
      - redis

  redis:
    image: redis:7-alpine
    restart: always
    ports:
      - "6379:6379"

  reverb:
    image: php:8.3-cli
    command: php /var/www/html/artisan reverb:start --host=0.0.0.0 --port=8080
    restart: always
    ports:
      - "8080:8080"
    volumes:
      - ./backend:/var/www/html
    depends_on:
      - app

  horizon:
    image: php:8.3-cli
    command: php /var/www/html/artisan horizon
    restart: always
    volumes:
      - ./backend:/var/www/html
    depends_on:
      - redis
      - app

  nginx:
    image: nginx:alpine
    restart: always
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./backend:/var/www/html
      - ./frontend/dist:/var/www/frontend
      - ./docker/nginx.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - app
      - reverb
```

---

## ۵. اتصال به سانترال تلفنی (Asterisk و Issabel)

### ۵.۱. تنظیم فایل `manager.conf` در سرور استریسک:
```ini
[general]
enabled = yes
port = 5038
bindaddr = 0.0.0.0

[voicereach]
secret = VoipSecretPass2026!
read = system,call,log,verbose,command,agent,user,config,dtmf
write = system,call,log,verbose,command,agent,user,config
```

### ۵.۲. ارسال رویدادها از سانترال به VoiceReach OS:
در دیال‌پلن استریسک (`extensions_custom.conf`) یا با استفاده از اسکریپت AGI / cURL رویدادهای تماس را به اندپوینت زیر ارسال کنید:
- `POST https://your-server.local/api/v1/calls/webhook`
- هدرهای موردنیاز:
  - `X-VoIP-Signature`: امضای HMAC-SHA256 بدنه با کلید مشترک
  - `X-VoIP-Timestamp`: زمان فعلی سیستم
  - `X-Idempotency-Key`: شناسه یونیک رخداد

---

## ۶. تنظیم سامانه پیامکی ایران (کاوه‌نگار و قاصدک)

در پنل تنظیمات سامانه یا فایل `.env`:
```env
KAVENEGAR_API_KEY=your_kavenegar_token_here
KAVENEGAR_SENDER=10008888
GHASEDAK_API_KEY=your_ghasedak_token_here
GHASEDAK_SENDER=30008888
```
سامانه دارای قابلیت **Failover خودکار** است؛ در صورت بروز قطعی در کاوه‌نگار، ارسال‌ها بدون توقف از طریق قاصدک مخابره می‌گردند.

---

## ۷. تست سلامت و زمان‌بندی پشتیبان‌گیری خودکار (Cron Jobs)

برای تست وضعیت زیرساخت در هر زمان:
```bash
php artisan voicereachos:health
# یا خروجی ساخت‌یافته:
php artisan voicereachos:health --json
```

برای تهیه دستی نسخه پشتیبان:
```bash
php artisan voicereachos:backup --retention-days=30
```

### کرون‌جاب لینوکس (`crontab -e`):
```cron
# اجرای زمان‌بندی‌های لاراول در هر دقیقه
* * * * * cd /var/www/html/backend && php artisan schedule:run >> /dev/null 2>&1

# پشتیبان‌گیری خودکار شبانه در ساعت ۰۲:۰۰ بامداد
0 2 * * * cd /var/www/html/backend && php artisan voicereachos:backup --retention-days=30 >> /dev/null 2>&1
```

---

## ۸. نکات امنیت شبکه در استقرارهای سازمانی

1. **شبکه ایزوله:** در مراکز درمانی و سازمان‌ها، پورت ۵۰۳۸ (AMI) استریسک را تنها برای آدرس IP سرور VoiceReach باز بگذارید.
2. **امضای رمزی ضبط مکالمات:** فایل‌های صوت ضبط شده در پوشه عمومی قرار ندارند و تنها از طریق لینک موقت و امضا شده (`temporarySignedRoute`) در دسترس کاربران دارای دسترسی شنود قرار می‌گیرند.
3. **فهرست DNC:** تمامی تماس‌های خروجی و کمپین‌ها قبل از ارسال در سطح کد با جدول `do_not_call_list` تطبیق داده شده و فیلتر می‌شوند.
