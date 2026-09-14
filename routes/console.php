<?php

use Illuminate\Support\Facades\Schedule;

// Phase 11 will add the actual overdue-installment scan here, e.g.:
//
// Schedule::command('loans:mark-overdue')
//     ->dailyAt('00:15')
//     ->withoutOverlapping()
//     ->onOneServer();
