<?php

namespace App\Console\Commands;

use App\Services\WebsiteStats;
use Illuminate\Console\Command;

class BackfillWebsiteStats extends Command
{
    protected $signature = 'stats:backfill';
    protected $description = 'Build or resume daily website statistics through yesterday';

    public function handle(WebsiteStats $stats): int
    {
        $this->info('Daily statistics complete through '.$stats->summarizeThroughYesterday());

        return self::SUCCESS;
    }
}
