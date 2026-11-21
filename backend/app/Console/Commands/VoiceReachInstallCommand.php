<?php

namespace App\Console\Commands;

use App\Enums\RoleEnum;
use App\Enums\UserStatusEnum;
use App\Models\Branch;
use App\Models\CompanyProfile;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class VoiceReachInstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'voicereachos:install 
                            {--force : Force execution without confirmation}
                            {--admin-name= : Name of the initial super admin}
                            {--admin-email= : Email of the initial super admin}
                            {--admin-phone= : Phone number of the initial super admin}
                            {--admin-password= : Password for the initial super admin}';

    /**
     * The console command description.
     */
    protected $description = 'نصب و راه‌اندازی کامل پایگاه‌داده، دسترسی‌ها و اطلاعات اولیه سامانه VoiceReach OS';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('========================================================');
        $this->info('          VoiceReach OS - Iranian SMB Voice OS         ');
        $this->info('========================================================');

        if (! $this->option('force') && ! $this->confirm('آیا از اجرای فرآیند نصب سامانه VoiceReach OS اطمینان دارید؟', true)) {
            $this->warn('فرآیند نصب لغو شد.');

            return self::FAILURE;
        }

        // 1. Generate Application Key if not present
        if (empty(config('app.key'))) {
            $this->info('1/5. در حال تولید کلید رمزنگاری اپلیکیشن (APP_KEY)...');
            Artisan::call('key:generate', ['--force' => true]);
        }

        // 2. Run Database Migrations
        $this->info('2/5. در حال اجرای مایگریشن‌های پایگاه‌داده...');
        Artisan::call('migrate', ['--force' => true]);
        $this->line(Artisan::output());

        // 3. Seed Roles and Permissions
        $this->info('3/5. در حال مقداردهی نقش‌ها و دسترسی‌های سیستمی...');
        Artisan::call('db:seed', [
            '--class' => RoleAndPermissionSeeder::class,
            '--force' => true,
        ]);

        // 4. Create Storage Symlink
        $this->info('4/5. در حال ایجاد لینک نمادین ذخیره‌سازی فایل‌ها...');
        try {
            Artisan::call('storage:link');
        } catch (\Exception $e) {
            $this->comment('لینک استوریج از قبل وجود دارد یا در این محیط پشتیبانی نمی‌شود.');
        }

        // 5. Initial Company & Admin Setup
        $this->info('5/5. بررسی و ایجاد حساب مدیر ارشد...');
        $admin = User::first();
        if (! $admin) {
            $name = $this->option('admin-name') ?: $this->ask('نام کامل مدیر ارشد', 'مدیر سیستم');
            $email = $this->option('admin-email') ?: $this->ask('ایمیل مدیر ارشد', 'admin@voicereach.local');
            $phone = $this->option('admin-phone') ?: $this->ask('شماره همراه مدیر ارشد', '09120000000');
            $password = $this->option('admin-password') ?: $this->secret('رمز عبور مدیر ارشد (حداقل ۸ کاراکتر)');

            if (empty($password) || strlen($password) < 8) {
                $password = 'Secret1234!';
                $this->comment('رمز پیش‌فرض Secret1234! تنظیم شد.');
            }

            $company = CompanyProfile::firstOrCreate([], [
                'name' => 'سازمان مرکزی',
                'phone' => $phone,
                'timezone' => 'Asia/Tehran',
                'invoice_prefix' => 'VR-',
                'invoice_counter' => 1000,
            ]);

            $branch = Branch::firstOrCreate(
                ['code' => 'MAIN'],
                [
                    'name' => 'شعبه مرکزی',
                    'phone' => $phone,
                    'timezone' => 'Asia/Tehran',
                    'is_active' => true,
                ]
            );

            $admin = User::create([
                'branch_id' => $branch->id,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make($password),
                'status' => UserStatusEnum::ACTIVE,
                'email_verified_at' => now(),
            ]);

            $admin->assignRole(RoleEnum::OWNER->value);
            $branch->update(['manager_id' => $admin->id]);

            $this->info("حساب کاربری مدیر ارشد با ایمیل {$email} با موفقیت ایجاد شد.");
        } else {
            $this->comment("مدیر ارشد با ایمیل {$admin->email} از قبل وجود دارد.");
        }

        $this->newLine();
        $this->info('✔ نصب و راه‌اندازی سامانه VoiceReach OS با موفقیت پایان یافت.');
        $this->info('جهت ورود به پنل، وب‌سرور یا سرویس Vite را اجرا نمایید.');
        $this->info('========================================================');

        return self::SUCCESS;
    }
}
