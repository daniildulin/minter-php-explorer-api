<?php

namespace App\Console;

use App\Console\Commands\AddressBalanceClientCommand;
use App\Console\Commands\BlocksQueueWorkerCommand;
use App\Console\Commands\CheckMinterNodeListCommand;
use App\Console\Commands\DeclareQueuesCommand;
use App\Console\Commands\FillBalanceTableCommand;
use App\Console\Commands\FillTxPerDayTableCommand;
use App\Console\Commands\PullBlockDataCommand;
use App\Console\Commands\PullMinterApiDataCommand;
use App\Console\Commands\TxPerDaySaveCommand;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        CheckMinterNodeListCommand::class,
        PullMinterApiDataCommand::class,
        FillTxPerDayTableCommand::class,
        TxPerDaySaveCommand::class,
        FillBalanceTableCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('transactions:per_count_save')->dailyAt('00:01');
    }
}
