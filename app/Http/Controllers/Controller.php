<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    public $ch;

    public function __construct(){
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, 100);
    }

    public function get($url, $params, $headers = []){
        curl_setopt($this->ch, CURLOPT_URL, $url."?".http_build_query($params));
        curl_setopt($this->ch, CURLOPT_POST, false);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
        $data = curl_exec($this->ch);

        if(curl_errno($this->ch)){
            print curl_error($this->ch);
        }else{
            curl_close($this->ch);
        }

        return $data;
    }


    public function post($url, $data, $headers = []){
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_POST, true);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
        $data = curl_exec($this->ch);

        if(curl_errno($this->ch)){
            print curl_error($this->ch);
        }else{
            curl_close($this->ch);
        }

        return $data;
    }

    public function format_number($value){
        return floatval(preg_replace("/[\,]/", "", $value));
    }

    public function previousDay($day){
        $previous_day = strtotime("$day -1 day");
        $previous_day_date = getdate($previous_day);

        if($previous_day_date["wday"] == 0 || $previous_day_date["wday"] == 6)
            return $this->previousDay(date('Y-m-d', $previous_day));
        else return date('Y-m-d', $previous_day);
    }

    public function nextDay($day){
        $previous_day = strtotime("$day +1 day");
        $previous_day_date = getdate($previous_day);

        if($previous_day_date["wday"] == 0 || $previous_day_date["wday"] == 6)
            return $this->nextDay(date('Y-m-d', $previous_day));
        else return date('Y-m-d', $previous_day);
    }

    public function previousDayJoin($day, $filter_date){
        return DB::table("dl")
            ->addSelect("code")
            ->addSelect(DB::raw("COUNT(*) as count"))
            ->whereRaw("(DAYOFWEEK('{$filter_date}') = 2 AND DATEDIFF('{$filter_date}', date) = {$day}+2) OR (DAYOFWEEK('{$filter_date}') > 2 AND DAYOFWEEK('{$filter_date}') <= 6 AND DATEDIFF('{$filter_date}', date) = {$day})")
            ->groupBy("code");
    }


    public function toTable($data, $mapping_label){

        if(empty($data)) return;

        print <<<EOF
<style>
.level-3{
    background-color: red;
}
.level-2{
    background-color: yellow;
}
.level-1{
   
}
</style>
EOF;

        print "<table border='1' cellpadding='5' cellspacing='0'>
<thead>
<tr>";


        foreach ($data[array_keys($data)[0]] as $key => $value){

            if(isset($mapping_label[$key]))

                print "<th style='text-transform: uppercase'>{$mapping_label[$key]}<br><small style='font-size: 9px'>{$key}</small></th>";
        }
        print "</tr>
</thead>
<tbody>";


        foreach($data as $tr){

            print "<tr>";

            foreach($tr as $key=> $td){

                if(!isset($mapping_label[$key])) continue;

                if(preg_match("/range|rate/", $key)) $td .= "%";
                print "<td>{$td}</td>";
            }
            print "</tr>";
        }

        print "</tbody></table>";
    }

}
