<?php

namespace App\Http\Controllers;


use App\Crawler\StockHelper;
use App\Dl;
use App\GeneralStock;
use App\Holiday;
use App\Jobs\Trading\ShortSell0;
use App\StockOrder;
use App\StockPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class OrderController extends Controller
{
    //

    public function test($filter_date = null, Request $request)
    {

        if(!$filter_date)
            if($request->filled("filter_date"))
                $filter_date = $request->filter_date;
            else $filter_date = date("Y-m-d");



        $openDeal = StockOrder::where("date", $filter_date)
            ->where("closed", "=", false)
            ->orderBy("id", "asc")
            ->get();

        $closeDeal = StockOrder::where("date", $filter_date)
            ->where("closed", true)
            ->orderBy("id", "asc")
            ->orderBy("tlong", "asc")
            ->get();


        return view("backend.place_order")->with(compact("openDeal","closeDeal", "filter_date"));
    }


    public function vendor_orders($filter_date = null, Request $request)
    {

        if(!$filter_date)
            if($request->filled("filter_date"))
                $filter_date = $request->filter_date;
            else $filter_date = date("Y-m-d");



        $openDeal = StockOrder::where("date", $filter_date)
            ->where("status", "=", StockOrder::SUCCESS)
            ->orderBy("id", "asc")
            ->get();


        return view("backend.vendor_orders")->with(compact("openDeal", "filter_date"));
    }
}
