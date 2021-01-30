<?php

namespace App\Console\Commands;

use App\StockVendors\FBS;
use Illuminate\Console\Command;

class OrderSell extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Order:sell {code} {qty?} {price?}';

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
        $code = $this->argument("code");
        $qty = $this->argument("qty") ? $this->argument("qty") : 1;

        $r = FBS::sell($code, $qty, $this->argument("price"));
        echo json_encode($r)."\n";
        return 0;
    }
}
