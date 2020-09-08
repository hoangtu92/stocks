<?php

namespace App\Http\Controllers;

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
}
