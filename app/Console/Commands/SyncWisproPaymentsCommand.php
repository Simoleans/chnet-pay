<?php

namespace App\Console\Commands;

use App\Jobs\SyncWisproPayments;
use Illuminate\Console\Command;

class SyncWisproPaymentsCommand extends Command
{
    protected $signature = 'wispro:sync-payments';

    protected $description = 'Sincroniza los pagos Wispro de los últimos 7 días';

    public function handle(): int
    {
        $from = now()->subDays(SyncWisproPayments::LOOKBACK_DAYS)->toDateString();
        $to = now()->toDateString();

       /*  if ($this->option('queue')) {
            SyncWisproPayments::dispatch();
            $this->info("Job encolado (últimos 7 días: {$from} a {$to}).");

            return self::SUCCESS;
        } */

        $this->info("Sincronizando pagos Wispro de los últimos 7 días ({$from} a {$to})...");
        SyncWisproPayments::dispatchSync();
        $this->info('Listo. Revisa payments_wispro, payment_invoice_wispro, invoices_wispro e invoice_items_wispro.');

        return self::SUCCESS;
    }
}
