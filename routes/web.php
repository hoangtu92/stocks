<?php


use App\Arav;
use App\Charts\StockChart;
use App\Charts\Test2Chart;
use App\Charts\TestChart;
use App\Crawler\StockHelper;
use App\GeneralPrice;
use App\Jobs\Crawl\CrawlAgent;
use App\Jobs\Crawl\CrawlDL;
use App\Jobs\Crawl\CrawlRealtimeGeneral;
use App\Jobs\Crawl\CrawlRealtimeStock;
use App\Jobs\CrawlYahooPrice;
use App\Jobs\TestQueue;
use App\Jobs\TestQueue2;
use App\StockOrder;
use App\StockPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get("/", "FrontController@order")->name("order");
Route::get('/data/{date?}', "FrontController@data")->name("data");
Route::get('/general/{date?}', "FrontController@generalStock")->name("general");
Route::get("/dl0/{filter_date?}", "StockController@dl0");

Route::get('/crawl/data/{date?}', "StockController@crawlData")->name("crawl_data_by_date");
Route::get('/crawl/arav/{date?}', "StockController@crawlArav")->name("crawl_arav");
Route::get('/crawl/dl/{date?}', "StockController@crawlDl")->name("crawl_dl");
Route::get('/crawl/xz/{date?}', "StockController@crawlXZ")->name("crawl_xz");

Route::get("/crawl/reAgency", "StockController@reCrawlAgency")->name("re_crawl_agency");

Route::get("/test/{filter_date?}", "OrderController@test")->name("test");


Route::post("/update_general_predict", "ActionController@update_general_predict")->name("update_general_predict");
Route::post("/update_final_predict", "ActionController@update_final_predict")->name("update_final_predict");
Route::post("/update_order", "ActionController@update_order")->name("update_order");
Route::post("/update_server_status", "ActionController@update_server_status")->name("update_server_status");
Route::post("/close_all_orders", "ActionController@close_orders")->name("close_all_orders");
Route::post("/close_order", "ActionController@close_order")->name("close_order");



Route::get("/redis-test", function (){

    # Redis::flushall();
    $general_start = StockHelper::getGeneralStart(date("Y-m-d"));
    $yesterday_final = StockHelper::getYesterdayFinal(date("Y-m-d"));
    $general_trend = Redis::get("General:trend");
    $current_general = StockHelper::getCurrentGeneralPrice(1606959145000);

    echo json_encode([
        "general_start" => $general_start,
        "yesterday_final" => $yesterday_final,
        "general_trend" => $general_trend,
        "current_general" => $current_general,
    ]);

    echo !(bool)Redis::get("is_holiday");


    #echo json_encode(Redis::lrange("Stock:DL0", 0, -1));

    #TestQueue::dispatch()->onQueue("default");
    #TestQueue2::dispatch()->onQueue("high");

    # CrawlDL::dispatch()->onQueue("high");

    #TestQueue::dispatch()->onQueue("low");
    #TestQueue2::dispatch();

    #CrawlYahooPrice::dispatchSync(1568);
    #CrawlAgent::dispatchNow("2020-11-30");
    /*$t = Redis::lrange('queues:high', 0, -1);
    foreach($t as $q){
        echo $q;
    }*/

    /*Redis::del("hash_test");
    #Redis::hmset("hash_test", \App\GeneralPrice::find(5)->toArray());


    Redis::lpush("Stock:DL1", 22);
    Redis::lpush("Stock:DL1", 44);
    $r = Redis::lrange("Stock:DL1", 0, -1);
    echo json_encode($r);*/


    /*$list2 = DB::table("dl")->join("stocks", "stocks.code", "=", "dl.code")
        ->addSelect("dl.code")
        ->addSelect("stocks.type")
        ->where("dl.final", ">", 10)
        ->where("dl.final", "<", 200)
        ->whereRaw("dl.agency IS NOT NULL")
        ->whereIn("dl.date", ['2020-11-30'])
        ->get()->toArray();

    $ll2 = [];
    foreach ($list2 as $st){
        Redis::lpush("testlist", $st->code);
        $ll2[] = $st->code;
    }*/

    #$r = Redis::lrange("testlist", 0, -1);
    #var_dump($r);

    /*Redis::flushall();

    $general_realtime = GeneralPrice::where("date", date("Y-m-d"))->get();
    foreach ($general_realtime as $generalPrice){
        Redis::hmset("General:realtime#{$generalPrice->time->format("YmdHi")}", $generalPrice->toArray());
    }

    $general = Redis::hgetall("General:realtime#202012020907");
    echo json_encode($general);*/


});

Route::get("/crawlYahooData", "StockController@crawlYahoo");

Route::get("/stock-data/{date}/{code}", function ($date, $code){
    $stocks = DB::table("stock_prices")
        ->addSelect(DB::raw("tlong as date"))
        ->addSelect(DB::raw("ROUND((best_ask_price + best_bid_price)/2, 2) as value"))
        ->addSelect("low")
        ->addSelect("high")
        ->addSelect("yesterday_final as y")
        ->where("date", $date)
        ->where("code", $code)
        ->orderBy("tlong")->get();
    return $stocks;

})->name("stock_data");

Route::get("/general-data/{date}", function ($date){
    $previous_date = StockHelper::previousDay($date);
    $stocks = DB::table("general_prices")
        ->addSelect(DB::raw("tlong as date"))
        ->addSelect("value")
        ->addSelect(DB::raw("(SELECT today_final FROM general_stocks gs WHERE gs.date = '{$previous_date}') as y"))
        ->addSelect("low")
        ->addSelect("high")
        ->where("date", $date)
        ->orderBy("tlong")->get();
    return $stocks;
})->name("general_data");


Route::get('/stock-chart/{date}/{code}', function ($date, $code)
{
    return view('chart')->with(compact("date", "code"));
})->name("stock_chart");

Route::get("/order-data/{date}/{code}", function ($date, $code){
    $stock_orders = DB::table("stock_orders")
        ->addSelect("tlong as start")
        ->addSelect("tlong2 as end")
        ->addSelect("sell")
        ->addSelect("buy")
        ->where("code", $code)
        ->where("date", $date)
        ->orderBy("tlong")
        ->get();

    return $stock_orders;
})->name("stock_order");
