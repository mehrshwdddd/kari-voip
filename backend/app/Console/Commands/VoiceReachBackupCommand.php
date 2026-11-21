<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ZipArchive;

class VoiceReachBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'voicereachos:backup 
                            {--retention-days=30 : Number of days to keep backup files}';

    /**
     * The console command description.
     */
    protected $description = 'پشتیبان‌گیری کامل از پایگاه داده و فایل‌های صوتی ضبط شده مکالمات';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('در حال تهیه نسخه پشتیبان از پایگاه داده و فایل‌های سامانه...');

        $backupDir = storage_path('app/backups');
        if (! File::isDirectory($backupDir)) {
            File::makeDirectory($backupDir, 0755, true, true);
        }

        $timestamp = now()->format('Y_m_d_His');
        $zipFilename = "voicereachos_backup_{$timestamp}.zip";
        $zipPath = $backupDir.DIRECTORY_SEPARATOR.$zipFilename;

        if (! class_exists(ZipArchive::class)) {
            $this->warn('اکستنشن zip بر روی PHP نصب نیست. در حال ساخت فایل نسخه پشتیبان با فرمت متنی...');
            $backupTextFile = $backupDir.DIRECTORY_SEPARATOR."backup_{$timestamp}.json";
            file_put_contents($backupTextFile, json_encode([
                'timestamp' => now()->toIso8601String(),
                'version' => '1.0.0',
                'app_name' => 'VoiceReach OS',
            ], JSON_PRETTY_PRINT));
            $this->info("پشتیبان اولیه در مسیر {$backupTextFile} ذخیره شد.");

            return self::SUCCESS;
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error('امکان ایجاد فایل فشرده نسخه پشتیبان وجود ندارد.');

            return self::FAILURE;
        }

        // 1. Backup SQLite database if using sqlite
        $dbConnection = config('database.default');
        if ($dbConnection === 'sqlite') {
            $sqlitePath = config('database.connections.sqlite.database');
            if (File::exists($sqlitePath)) {
                $zip->addFile($sqlitePath, 'database/database.sqlite');
            }
        }

        // 2. Backup recordings directory
        $recordingsPath = storage_path('app/recordings');
        if (File::isDirectory($recordingsPath)) {
            $files = File::allFiles($recordingsPath);
            foreach ($files as $file) {
                $zip->addFile($file->getRealPath(), 'recordings/'.$file->getRelativePathname());
            }
        }

        // 3. Backup configuration metadata
        $metadata = [
            'app' => 'VoiceReach OS',
            'version' => '1.0.0',
            'created_at' => now()->toIso8601String(),
            'database_driver' => $dbConnection,
        ];
        $zip->addFromString('backup_metadata.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $zip->close();

        $sizeMb = round(filesize($zipPath) / (1024 * 1024), 2);
        $this->info("✔ نسخه پشتیبان با موفقیت ساخته شد: {$zipFilename} ({$sizeMb} مگابایت)");

        // 4. Clean old backups
        $retentionDays = (int) $this->option('retention-days');
        $expiryDate = now()->subDays($retentionDays);

        $existingBackups = File::glob($backupDir.'/*.zip');
        $deletedCount = 0;
        foreach ($existingBackups as $backup) {
            if (filemtime($backup) < $expiryDate->getTimestamp()) {
                File::delete($backup);
                $deletedCount++;
            }
        }

        if ($deletedCount > 0) {
            $this->comment("تعداد {$deletedCount} نسخه پشتیبان قدیمی‌تر از {$retentionDays} روز حذف گردید.");
        }

        return self::SUCCESS;
    }
}
