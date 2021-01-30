<?php


namespace App\StockVendors;


use Backpack\Settings\app\Models\Setting;
use Illuminate\Support\Facades\Redis;

class SelectedVendor implements Vendor
{

    /**
     * @inheritDoc
     */
    public static function buy(string $code, int $qty, $price = "")
    {
        // TODO: Implement buy() method.

        if (Setting::get('server_status') == '0') {
            return self::fakeResponse();
        }

        $result = '';
        switch(env("VENDOR")){
            case "FBS":
                $result = FBS::buy($code, $qty, $price);
                break;
            case "ESUN":
                break;
            case "HNS":
                break;
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public static function sell(string $code, int $qty, $price = "")
    {
        // TODO: Implement sell() method.

        if (Setting::get('server_status') == '0') {
            return self::fakeResponse();
        }

        $result = null;
        switch(env("VENDOR")){
            case "FBS":
                $result = FBS::sell($code, $qty, $price);
                break;
            case "ESUN":
            case "HNS":
                break;
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public static function cancel(string $OID, string $orderNo)
    {
        // TODO: Implement cancel() method.

        if (Setting::get('server_status') == '0') {
            return self::fakeResponse($OID, $orderNo);
        }

        $result = '';
        switch(env("VENDOR")){
            case "FBS":
                $result = FBS::cancel($OID, $orderNo);
                break;
            case "ESUN":
                break;
            case "HNS":
                break;
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    public static function login()
    {
        // TODO: Implement login() method.
        $result = '';
        switch(env("VENDOR")){
            case "FBS":
                $result = FBS::login();
                break;
            case "ESUN":
                break;
            case "HNS":
                break;
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    public static function logout()
    {
        // TODO: Implement logout() method.
        $result = '';
        switch(env("VENDOR")){
            case "FBS":
                $result = FBS::logout();
                break;
            case "ESUN":
                break;
            case "HNS":
                break;
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    public static function list()
    {
        // TODO: Implement list() method.
        $result = '';
        switch(env("VENDOR")){
            case "FBS":
                $result = FBS::list();
                break;
            case "ESUN":
                break;
            case "HNS":
                break;
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    public static function all()
    {
        // TODO: Implement all() method.

        $result = '';
        switch(env("VENDOR")){
            case "FBS":
                $result = FBS::all();
                break;
            case "ESUN":
                break;
            case "HNS":
                break;
        }
        return $result;
    }

    public static function fakeResponse($OID = null, $OrderNo=null): array
    {
        return [
            "Status" => true,
            "OID" => $OID ? $OID : str_pad(mt_rand(1,99999999),8,'0',STR_PAD_LEFT),
            "OrderNo" => $OrderNo ? $OrderNo : "o".str_pad(mt_rand(1,9999),4,'0',STR_PAD_LEFT),
            "OriginData" => null
        ];
    }
}
