<?php

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

Route::get('/data/{date?}', "StockController@data");
Route::get('/arav/{date?}', "StockController@getArav");
Route::get('/dl/{date?}', "StockController@getDl");
Route::get('/crawlDataByDate/{date?}', "StockController@crawlDataByDate");
Route::get("/order", "StockController@order");
Route::get("/crawlOrderByDate/{date?}", "StockController@crawlOrderByDate");
Route::get("/crawlOrderToday", "StockController@crawlOrderToday");
