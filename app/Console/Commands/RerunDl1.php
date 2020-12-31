<?php

namespace App\Console\Commands;

use App\Crawler\StockHelper;
use App\Holiday;
use App\Jobs\Rerun\Dl1;
use App\Jobs\Trading\TickShortSell1;
use App\StockOrder;
use App\StockPrice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RerunDl1 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rerun:dl1 {filter_date?} {code?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        $filter_date = $this->argument("filter_date");
        $code = $this->argument("code");
        if (!$filter_date)
            $filter_date = date("Y-m-d");

        Dl1::dispatchNow($filter_date, $code);
    }

}
