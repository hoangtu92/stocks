<?php

namespace App\Console\Commands;

use App\StockVendors\FBS;
use App\StockVendors\SelectedVendor;
use Illuminate\Console\Command;

class OrderCancel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Order:cancel {oid} {orderNo}';

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
        $oid = $this->argument("oid");
        $orderNo = $this->argument("orderNo");
        $r = SelectedVendor::cancel($oid, $orderNo);
        echo json_encode($r)."\n";
        return 0;
    }
}
