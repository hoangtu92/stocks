<?php

use App\Jobs\ImportCMoney;
use App\Jobs\ImportCMoneyGeneral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post("/import_cmoney/{code}", function ($code, Request $request){
    $request->validate([
        "json" => "required"
    ]);
    $data = json_decode($request->json);

    //var_dump($data);
    ImportCMoney::dispatchNow($code, $data);
    return $data->DataPrice;
})->middleware(['cors']);


Route::post("/import_cm_general", function (Request $request){
    $request->validate([
        "json" => "required"
    ]);
    $data = json_decode($request->json);

    //var_dump($data);
    ImportCMoneyGeneral::dispatch($data)->onQueue("high");
    return $data->DataPrice;
})->middleware(['cors']);

Route::post("/vendor_post_back", function (Request $request){
    \Illuminate\Support\Facades\Log::info("Vendor post back: " .json_encode($request->toArray()));
});
