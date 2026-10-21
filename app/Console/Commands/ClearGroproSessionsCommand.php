<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ClearGroproSessionsCommand extends Command
{
    protected $signature = 'gropro:clear-sessions';

    protected $description = 'Wipe all Gropro sessions (file and/or database) — LAN prototype reset';

    public function handle(): int
    {
        $driver = (string) config('session.driver');
        $cleared = [];

        $dir = storage_path('framework/sessions');
        if (is_dir($dir)) {
            foreach (File::files($dir) as $file) {
                File::delete($file->getPathname());
            }
            $cleared[] = 'file:'.$dir;
        }

        try {
            if (DB::getSchemaBuilder()->hasTable('sessions')) {
                DB::table('sessions')->truncate();
                $cleared[] = 'database:sessions';
            }
        } catch (\Throwable) {
            // DB may be unavailable; file wipe is enough for SESSION_DRIVER=file.
        }

        $this->info('Sessions cleared (driver='.$driver.'): '.implode(', ', $cleared ?: ['nothing']));
        $this->line('Also clear phone cookies for your LAN IP, then reopen http://IP:8000/');

        return self::SUCCESS;
    }
}
