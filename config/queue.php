<?php

return [

    'default' => env('QUEUE_CONNECTION', 'redis'),

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'queue',
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => 90,
            'block_for' => null,
            'after_commit' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------
    | Why after_commit matters here
    |--------------------------------------------------------------------
    |
    | Every domain event in this app (LoanApproved, LoanDisbursed,
    | RepaymentReceived, LoanCompleted) is dispatched from INSIDE the same
    | DB::transaction() as the state change it describes — that's
    | deliberate, so "the event fired" and "the change actually happened"
    | can't diverge if something later in the transaction fails and rolls
    | back.
    |
    | But `event()` fires its listeners immediately, synchronously, at
    | the point it's called — including pushing any queued jobs those
    | listeners dispatch onto Redis right then, mid-transaction. Without
    | `after_commit`, a fast queue worker could dequeue and start
    | executing SendLoanApprovalNotification before the surrounding
    | transaction has actually committed — and if that transaction then
    | rolled back, the notification job would be acting on a loan
    | application that, as far as the database is concerned, was never
    | actually approved.
    |
    | Setting `after_commit => true` on the queue connection tells
    | Laravel to hold any job dispatched from within a transaction until
    | that transaction commits, and to silently drop it if the
    | transaction rolls back instead. This is a connection-level setting
    | (applies to every job on this connection) rather than something
    | each Job class has to remember to opt into individually — see
    | docs/queues.md.
    |
    */

    'batching' => [
        'database' => env('DB_CONNECTION', 'pgsql'),
        'table' => 'job_batches',
    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'pgsql'),
        'table' => 'failed_jobs',
    ],

];
