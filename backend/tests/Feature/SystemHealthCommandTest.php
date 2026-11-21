<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SystemHealthCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_command_runs_successfully(): void
    {
        $this->artisan('voicereachos:health')
            ->expectsOutputToContain('گزارش وضعیت سلامت سامانه VoiceReach OS:')
            ->assertSuccessful();

        $this->artisan('voicereachos:health', ['--json' => true])
            ->assertSuccessful();
    }

    public function test_backup_command_creates_valid_archive(): void
    {
        $this->artisan('voicereachos:backup', ['--retention-days' => 7])
            ->expectsOutputToContain('نسخه پشتیبان با موفقیت ساخته شد')
            ->assertSuccessful();

        $backupDir = storage_path('app/backups');
        $this->assertTrue(File::isDirectory($backupDir));

        $backups = File::glob($backupDir.'/*.zip');
        $this->assertNotEmpty($backups);

        // Clean up test backup
        foreach ($backups as $file) {
            File::delete($file);
        }
    }
}
