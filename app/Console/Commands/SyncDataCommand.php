<?php

namespace App\Console\Commands;

use App\Jobs\SyncDataJob;
use Illuminate\Console\Command;

class SyncDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync INPUT sheet into the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        SyncDataJob::dispatch();

        $this->info('Sync job dispatched.');

        return self::SUCCESS;
    }
}
