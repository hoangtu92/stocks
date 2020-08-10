<?php


namespace App\Crawler;


class Crawler
{

    public function getDate($date){
        if(!$date) {
            $date = date_create(now());
        }
        if(is_string($date)){
            $date = date_create($date);
        }

        $year = $date->format("Y");
        $month = $date->format("m");
        $day = $date->format("d");
        $tw_year = $year - 1911;

        return [
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'tw_year' => $tw_year
        ];
    }

}
