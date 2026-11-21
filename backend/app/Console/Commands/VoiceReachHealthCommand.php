<?php

namespace App\Console\Commands;

use App\Models\Integration;
use App\Models\SmsProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class VoiceReachHealthCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'voicereachos:health {--json : Output result in JSON format}';

    /**
     * The console command description.
     */
    protected $description = 'بررسی وضعیت سلامت فنی، ارتباطات پایگاه‌داده، صف، استوریج و ارائه‌دهندگان مخابراتی سامانه';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $checks = [];
        $overallHealthy = true;

        // 1. Database Check
        $dbStart = microtime(true);
        try {
            DB::connection()->getPdo();
            $dbDuration = round((microtime(true) - $dbStart) * 1000, 2);
            $checks[] = [
                'component' => 'پایگاه داده (Database)',
                'status' => 'OK',
                'details' => config('database.default')." ({$dbDuration} ms)",
            ];
        } catch (\Exception $e) {
            $overallHealthy = false;
            $checks[] = [
                'component' => 'پایگاه داده (Database)',
                'status' => 'FAILED',
                'details' => $e->getMessage(),
            ];
        }

        // 2. Cache Check
        try {
            $cacheKey = 'health_check_'.time();
            Cache::put($cacheKey, 'ok', 10);
            $val = Cache::get($cacheKey);
            Cache::forget($cacheKey);

            if ($val === 'ok') {
                $checks[] = [
                    'component' => 'حافظه موقت (Cache)',
                    'status' => 'OK',
                    'details' => 'درایور '.config('cache.default'),
                ];
            } else {
                throw new \Exception('مقدار واکشی شده از کش صحیح نبود.');
            }
        } catch (\Exception $e) {
            $overallHealthy = false;
            $checks[] = [
                'component' => 'حافظه موقت (Cache)',
                'status' => 'WARNING',
                'details' => $e->getMessage(),
            ];
        }

        // 3. Storage Permissions
        $storagePaths = [
            'storage/app' => storage_path('app'),
            'storage/framework' => storage_path('framework'),
            'storage/logs' => storage_path('logs'),
        ];
        $allWritable = true;
        foreach ($storagePaths as $name => $path) {
            if (! File::isDirectory($path)) {
                File::makeDirectory($path, 0755, true, true);
            }
            if (! is_writable($path)) {
                $allWritable = false;
            }
        }

        $checks[] = [
            'component' => 'دسترسی دیسک (Storage)',
            'status' => $allWritable ? 'OK' : 'FAILED',
            'details' => $allWritable ? 'تمام مسیرها قابل نوشتن هستند' : 'دسترسی نوشتن به برخی مسیرهای storage محدود است',
        ];
        if (! $allWritable) {
            $overallHealthy = false;
        }

        // 4. VoIP / PBX Integration
        $pbx = Integration::where('type', 'voip')->where('status', 'active')->first();
        $checks[] = [
            'component' => 'ارتباط سانترال (VoIP PBX)',
            'status' => $pbx ? 'OK' : 'WARNING',
            'details' => $pbx ? "آداپتور فعال: {$pbx->name}" : 'پیکربندی ویپ ثبت نشده یا غیرفعال است',
        ];

        // 5. SMS Providers
        $smsCount = SmsProvider::where('is_active', true)->count();
        $checks[] = [
            'component' => 'سامانه پیامکی (SMS Gateway)',
            'status' => $smsCount > 0 ? 'OK' : 'WARNING',
            'details' => $smsCount > 0 ? "تعداد درگاه فعال: {$smsCount}" : 'هیچ درگاه پیامکی فعالی تعریف نشده است',
        ];

        // 6. Security & App Key
        $hasKey = ! empty(config('app.key'));
        $checks[] = [
            'component' => 'کلید رمزنگاری (App Key)',
            'status' => $hasKey ? 'OK' : 'FAILED',
            'details' => $hasKey ? 'تنظیم شده' : 'APP_KEY در فایل .env تعریف نشده است',
        ];
        if (! $hasKey) {
            $overallHealthy = false;
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'healthy' => $overallHealthy,
                'timestamp' => now()->toIso8601String(),
                'checks' => $checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $overallHealthy ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->info('گزارش وضعیت سلامت سامانه VoiceReach OS:');
        $this->table(['بخش سیستم', 'وضعیت', 'توضیحات تکمیلی'], $checks);

        if ($overallHealthy) {
            $this->info('✔ کلیه سرویس‌های حیاتی سامانه با موفقیت در حال کار هستند.');

            return self::SUCCESS;
        }

        $this->error('✖ برخی از مؤلفه‌های سیستم با مشکل مواجه شده‌اند.');

        return self::FAILURE;
    }
}
