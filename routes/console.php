<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('loans:mark-overdue')
    ->dailyAt('00:15')
    // Two overlapping runs could both try to lock and process the same
    // installment — the row lock in OverdueCheckService::processInstallment()
    // would make that SAFE (the second run's lock acquisition would just
    // wait, then see penalty_due already set and skip it), but there's no
    // reason to pay for two full scans of the table when one is running
    // long. withoutOverlapping() is a performance guard on top of a
    // correctness guarantee that already holds without it.
    ->withoutOverlapping()
    // Relevant once this runs across multiple app-container replicas in
    // production — ensures only one of them actually fires the command
    // rather than every replica running its own copy at 00:15.
    ->onOneServer();
