<?php

use App\Agent;
use App\Crawler\DLExcludeFilter;
use App\Crawler\DLIncludeFilter;
use App\Crawler\CrawlLargeTradeRateSell;
use App\Crawler\RealTime;
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
Route::get("/crawl/order/{key}/{date?}", "StockController@crawlOrder")->name("crawl_order");
Route::get('/crawl/arav/{date?}', "StockController@crawlArav")->name("crawl_arav");
Route::get('/crawl/dl/{date?}', "StockController@crawlDl")->name("crawl_dl");
Route::get('/crawl/xz/{date?}', "StockController@crawlXZ")->name("crawl_xz");
Route::get('/crawl/agency/{date?}', "StockController@crawlAgency")->name("crawl_agency");

Route::get('/crawl/generalStock/{filter_date?}/{key?}', "StockController@crawlGeneralStock")->name("general_stock");
Route::get('/crawl/generalStockToday/{key}', "StockController@crawlGeneralStockToday")->name("general_stock_today");
Route::get('/crawl/generalStockFinal/{date}', "StockController@crawlGeneralStockFinal")->name("general_stock_final");
Route::get("/crawl/reAgency", "StockController@reCrawlAgency")->name("re_crawl_agency");

Route::get("/test", "OrderController@test")->name("test");
Route::get("/place_order", "OrderController@place_order");
Route::post("/update_general_predict", "ActionController@update_general_predict")->name("update_general_predict");
Route::get("tt", function (){
   echo date("Y-m-d H:i:s A");
    $realTime = new RealTime();
    $realTime->monitor();
});
