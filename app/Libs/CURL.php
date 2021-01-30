<?php


namespace App\Libs;


use Illuminate\Support\Facades\Log;

class CURL
{

    /**
     * @param $url
     * @param false $proxy
     * @return bool|string
     */
    public static function get($url, $proxy=false){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        if($proxy)
            curl_setopt($ch, CURLOPT_PROXY, $proxy);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        $data = curl_exec($ch);

        if(curl_error($ch)){
            Log::error(curl_error($ch));
        }

        curl_close($ch);

        return json_decode($data);
    }


    /**
     * @param $url
     * @param array $data
     * @param false $proxy
     * @return bool|string
     */
    public static function post($url, $data = [], $proxy = false){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        if($proxy)
            curl_setopt($ch, CURLOPT_PROXY, $proxy);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_POST, 1);

        $data_string = json_encode($data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string))
        );

        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);

        $data = curl_exec($ch);

        if(curl_error($ch)){
            Log::error(curl_error($ch));
        }

        curl_close($ch);

        return json_decode($data);
    }


    /**
     * @param $url
     * @param array $data
     * @param false $proxy
     * @return bool|string
     */
    public static function delete($url, $data = [], $proxy = false){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        if($proxy)
            curl_setopt($ch, CURLOPT_PROXY, $proxy);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "delete");

        $data_string = json_encode($data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string))
        );

        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);

        $data = curl_exec($ch);

        if(curl_error($ch)){
            Log::error(curl_error($ch));
        }

        curl_close($ch);

        return json_decode($data);
    }
}
