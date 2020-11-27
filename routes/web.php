<?php

use App\Crawler\DLExcludeFilter;
use App\Crawler\DLIncludeFilter;
use App\Crawler\RealTime\RealtimeDL0;
use App\Dl;
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
Route::get("/place_order_dl0/{filter_date?}", "OrderController@place_order_dl0");
Route::post("/update_general_predict", "ActionController@update_general_predict")->name("update_general_predict");
Route::post("/update_final_predict", "ActionController@update_final_predict")->name("update_final_predict");
Route::post("/update_order", "ActionController@update_order")->name("update_order");
Route::post("/update_server_status", "ActionController@update_server_status")->name("update_server_status");


Route::get("/dl0/{filter_date?}", "StockController@dl0");
Route::get("/cmoney/{code}/{filter_date?}", "StockController@cmoney");
Route::post("/close_all_orders", "ActionController@close_orders")->name("close_all_orders");
Route::post("/close_order", "ActionController@close_order")->name("close_order");
