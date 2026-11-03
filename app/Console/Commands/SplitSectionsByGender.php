<?php

namespace App\Console\Commands;

use App\Services\SectionSplitter;
use Illuminate\Console\Command;

class SplitSectionsByGender extends Command
{
    protected $signature = 'school:split-sections';

    protected $description = 'تقسيم الشعب: مختلط حتى الخامس، ومن السادس فما فوق إناث صباحي وذكور مسائي';

    public function handle(SectionSplitter $splitter): int
    {
        $splitter->apply();
        foreach ($splitter->log as $line) {
            $this->line($line);
        }
        $this->info('تم تقسيم الشعب.');

        return self::SUCCESS;
    }
}
