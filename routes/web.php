<?php


use App\Crawler\StockHelper;
use App\StockVendors\SelectedVendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

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

Route::get("/crawlYahooData", "StockController@crawlYahoo");

Route::get("/stock-data/{date}/{code}", function ($date, $code){
    $stocks = DB::table("stock_prices")
        ->addSelect(DB::raw("tlong as date"))
        ->addSelect(DB::raw("ROUND((best_ask_price + best_bid_price)/2, 2) as value"))
        ->addSelect("open")
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


Route::get("/ip", function (Request $request){
   echo $request->getClientIp();
});

Route::get("/test-proxy", function (){
    $url = 'http://st8.fun/ip';
    echo StockHelper::get_content($url);
});


Route::get("/redis-test", function (){

    var_dump(\Illuminate\Support\Facades\Queue::size("low"));

    /*$r = Redis::keys("Stock*");

    $a = [];

    foreach($r as $value){
        echo $value."<br>";
        $a[] = Redis::hgetall(str_replace("dl0_strategy_1_database_", "", $value));
    }

    var_dump($a);*/


});
