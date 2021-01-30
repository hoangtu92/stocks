<?php

namespace App\Console\Commands;

use App\StockVendors\SelectedVendor;
use Illuminate\Console\Command;

class VendorLogin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Vendor:login';

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
        $r = SelectedVendor::login();
        echo json_encode($r)."\n";
        return 0;
    }
}
