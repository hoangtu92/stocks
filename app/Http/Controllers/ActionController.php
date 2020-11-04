<?php

namespace App\Http\Controllers;

use App\Dl;
use App\GeneralStock;
use Illuminate\Http\Request;
use Backpack\Settings\app\Models\Setting;
use Illuminate\Support\Facades\Redirect;

class ActionController extends Controller
{

    public function update_final_predict(Request $request){

        if($request->filled("predict_final")){
            foreach ($request->predict_final as $date => $value){
                $gs = GeneralStock::where("date", $date)->first();
                $gs->predict_final = $value;
                $gs->save();
            }

        }
        if($request->filled("custom_general_predict")){
            foreach ($request->custom_general_predict as $date => $value){
                $gs = GeneralStock::where("date", $date)->first();
                $gs->custom_general_predict = $value;
                $gs->save();
            }
        }

        return redirect()->back();
    }

    public function update_order(Request $request){
        if($request->filled("borrow_ticket")){

            foreach($request->borrow_ticket as $code => $borrow_ticket){
                $stock = Dl::where("code", $code)->where("dl_date", $request->date)->first();
                if($stock){
                    $stock->borrow_ticket = $borrow_ticket == 1;
                    $stock->save();
                }
            }
        }

        return redirect()->back()->with("success", "Field updated");
    }

    public function update_server_status(Request $request){
        $request->validate([
            "server_status" => "required"
        ]);

        echo $request->server_status;
        if($request->server_status){
            Setting::set('server_status', '1');
    }
        else{
            Setting::set('server_status', '0');
        }

        return Redirect::back()->with(["message" => "Server status updated"]);
    }
}
