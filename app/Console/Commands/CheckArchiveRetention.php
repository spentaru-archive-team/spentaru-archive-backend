<?php

namespace App\Console\Commands;

use App\Models\Archive;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('archives:check-archive-retention')]
#[Description('Command description')]
class CheckArchiveRetention extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        Archive::where('retention_status', 'active')->whereDate('retention_due_date', '<=', now()->toDateString())->update([
        'retention_status' => 'ready_for_destruction',
        ]);
    }
}
