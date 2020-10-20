<?php

use App\Crawler\DLExcludeFilter;
use App\Crawler\DLIncludeFilter;
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
Route::get("tt", function () {
    $crawler = new App\Crawler\Crawler();
    $r = [];

    $list = $crawler->getStocksURL();

    foreach ($list as $l){
        echo $l->url."<br>";
        /*$rs = json_decode($crawler->get_content($l->url));
        if(isset($rs->msgArray)){
            $r = array_merge($r, $rs->msgArray);
        }*/

        //sleep(1);
    }

    //echo json_encode($r);

});

Route::get("/tt2", function () {
    $crawler = new App\Crawler\Crawler();
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

    echo $crawler->curlGet("http://google.com");


});

Route::get("/ip", function (\Illuminate\Http\Request $request){
   echo $request->getClientIp();
});
