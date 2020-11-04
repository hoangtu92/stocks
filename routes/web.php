<?php

use App\Crawler\DLExcludeFilter;
use App\Crawler\DLIncludeFilter;
use App\Crawler\RealTime\RealtimeDL0;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

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

Route::get('/crawl/data/{date?}', "StockController@crawlData")->name("crawl_data_by_date");
Route::get('/crawl/arav/{date?}', "StockController@crawlArav")->name("crawl_arav");
Route::get('/crawl/dl/{date?}', "StockController@crawlDl")->name("crawl_dl");
Route::get('/crawl/xz/{date?}', "StockController@crawlXZ")->name("crawl_xz");
Route::get('/crawl/agency/{date?}', "StockController@crawlAgency")->name("crawl_agency");
Route::get('/crawl/holiday/{year?}', "StockController@crawlHoliday")->name("crawl_holiday");

Route::get("/crawl/reAgency", "StockController@reCrawlAgency")->name("re_crawl_agency");

Route::get("/test/{filter_date?}", "OrderController@test")->name("test");
Route::get("/place_order/{filter_date?}", "OrderController@place_order");
Route::post("/update_general_predict", "ActionController@update_general_predict")->name("update_general_predict");
Route::post("/update_final_predict", "ActionController@update_final_predict")->name("update_final_predict");
Route::post("/update_order", "ActionController@update_order")->name("update_order");
Route::post("/update_server_status", "ActionController@update_server_status")->name("update_server_status");
Route::get("tt", function () {
    $realTime = new RealtimeDL0();
    $realTime->monitor();
});

Route::get("/test-proxy", function (Request $request) {
    //$crawler = new App\Crawler\Crawler();
    /*$r = $crawler->curlGet("http://www.cmoney.tw/notice/chart/stock-chart-service.ashx", [
        "id" => 6233,
        "date" => "",
        "action" => "r",
        "ck" => "LKo3fMRv4ODb{VRQeQnAWNCjuR8nMAk7xMUR0JDVQAorTQVcsHCMHWjMpOErz",
        "_" => 1601889319824
    ], [
        "referer" => "http://www.cmoney.tw/notice/chart/stockchart.aspx?action=r&id=6233",
        "accept" => "application/json, text/javascript, ",
    ]);

    var_dump($r);*/

    //echo $crawler->previousDay("2020-10-05");

    $proxy = [
        "87.101.81.115:8800",
        "87.101.82.7:8800",
        "87.101.81.200:8800",
        "87.101.80.52:8800",
        "87.101.81.50:8800",
        "87.101.82.104:8800",
        "87.101.81.217:8800",
        "87.101.81.24:8800",
        "87.101.81.88:8800",
        "87.101.80.98:8800",
        "87.101.83.49:8800",
        "87.101.80.54:8800",
        "87.101.82.149:8800",
        "87.101.80.202:8800",
        "87.101.83.106:8800",
        "87.101.82.108:8800",
        "87.101.80.65:8800",
        "87.101.83.117:8800",
        "87.101.82.27:8800",
        "87.101.83.7:8800",
        "87.101.83.224:8800",
        "87.101.83.68:8800",
        "87.101.80.60:8800",
        "87.101.83.133:8800",
        "87.101.82.134:8800",
    ];

    $p = rand(0, count($proxy)-1);
    echo "Using proxy: {$proxy[$p]}<br>";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://st8.fun/ip");
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_PROXY, $proxy[$p]);
    $data = curl_exec($ch);

    if(curl_errno($ch)){
        print curl_error($ch);
    }else{
        curl_close($ch);
    }

    echo $data;


});

Route::get("/ip", function (Request $request){
   echo $request->getClientIp();
});
