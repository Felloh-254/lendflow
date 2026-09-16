<?php

namespace App\Listeners;

use App\Events\LoanCompleted;
use App\Jobs\GenerateStatement;

class GenerateStatementListener
{
    public function handle(LoanCompleted $event): void
    {
        GenerateStatement::dispatch($event->loan);
    }
}
