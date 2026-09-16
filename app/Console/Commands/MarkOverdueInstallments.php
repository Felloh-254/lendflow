<?php

namespace App\Console\Commands;

use App\Services\OverdueCheckService;
use Illuminate\Console\Command;

/**
 * What it is: an Artisan command Laravel's scheduler invokes on a timer.
 *
 * Why we need it: nothing in the HTTP-driven parts of this API has any
 * reason to notice that a due date has quietly passed — there's no
 * request that would trigger that check. A scheduled job is the only
 * mechanism that runs "on its own", driven by the calendar rather than
 * by a user action.
 *
 * How it's safe to run repeatedly: every bit of state this command
 * writes (an installment's penalty_due, a loan's overdue status) is
 * guarded so a second run — whether from a manual re-trigger, a
 * misconfigured double schedule entry, or a retry after a transient
 * failure — finds nothing left to do for anything already processed.
 * See OverdueCheckService's docblock for the specific guard. The
 * scheduler registration in routes/console.php additionally uses
 * `withoutOverlapping()` so two instances of this command can't even run
 * concurrently against each other in the first place — defense in depth
 * on top of, not instead of, the guard actually being correct.
 */
class MarkOverdueInstallments extends Command
{
    protected $signature = 'loans:mark-overdue';

    protected $description = 'Find unpaid installments past their due date, apply penalties, and mark affected loans overdue.';

    public function handle(OverdueCheckService $service): int
    {
        $result = $service->run();

        $this->info(sprintf(
            'Processed %d overdue installment(s); %d loan(s) newly marked overdue.',
            $result['installments_processed'],
            $result['loans_marked_overdue'],
        ));

        return self::SUCCESS;
    }
}
