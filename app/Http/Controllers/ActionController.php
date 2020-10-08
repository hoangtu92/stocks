<?php

namespace App\Http\Controllers;

use App\Dl;
use App\GeneralStock;
use Illuminate\Http\Request;

class ActionController extends Controller
{
    public function update_general_predict(Request $request){
        $request->validate([
           "general_predict" => "numeric|required",
            "date" => "required"
        ]);

        $generalStock = GeneralStock::where("date", $request->date)->first();
        if($generalStock){
            $generalStock->general_predict = $request->general_predict;
            $generalStock->save();
        }

        return redirect()->back()->with("success", "Field updated");
    }

    public function update_final_predict(Request $request){

        if($request->filled("predict_final")){
            foreach ($request->predict_final as $date => $value){
                $gs = GeneralStock::where("date", $date)->first();
                $gs->predict_final = $value;
                $gs->save();
            }

        }

        return redirect()->back();
    }

    public function update_order(Request $request){
        if($request->filled("borrow_ticket")){

            foreach($request->borrow_ticket as $code => $borrow_ticket){
                $stock = Dl::where("code", $code)->where("date", $request->date)->first();
                if($stock){
                    $stock->borrow_ticket = $borrow_ticket == 1;
                    $stock->save();
                }
            }
        }

        return redirect()->back()->with("success", "Field updated");
    }
}
