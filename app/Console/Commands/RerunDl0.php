<?php

namespace App\Console\Commands;

use App\Jobs\Rerun\Dl0;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;


class RerunDl0 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rerun:dl0 {filter_date?} {code?} {async?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected $filter_date;

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

        Redis::flushall();
        $filter_date = $this->argument("filter_date");
        $code = $this->argument("code");
        if (!$filter_date)
            $filter_date = date("Y-m-d");

        if($this->argument('async') == 'async'){
            Dl0::dispatch($filter_date, 0, $code)->onQueue("high");
        }
        else{
            Dl0::dispatchNow($filter_date, 1, $code);
        }

    }
}
