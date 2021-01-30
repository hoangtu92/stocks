<?php


namespace App\StockVendors;


use App\Libs\CURL;
use Backpack\Settings\app\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class FBS implements Vendor
{


    /**
     * @param $code
     * @param $qty
     * @param string $price
     * @return mixed
     */
    public static function buy($code, $qty, $price = "")
    {
        // TODO: Implement buy() method.

        Log::debug("Buy back for stock: {$code} ");

        $url = env("VENDOR_URL") . "FBS/order";
        $r = CURL::post($url, ["code" => $code, "qty" => $qty, "price" => $price, "bs" => "B"]);

        if (isset($r->Result->Data)) {
            $result = [
                "Status" => true,
                "CreateTime" => $r->Result->Data->Row->{"@CreateTime"},
                "OID" => $r->Result->Data->Row->{"@OID"},
                "OrderNo" => $r->Result->Data->Row->{"@OrderNo"},
                "OriginData" => $r
            ];
        } elseif (isset($r->Result->Error)) {
            $result = [
                "Status" => false,
                "Message" => "{$r->Result->Error->{"@Msg"}}",
                "OriginData" => $r
            ];
        } else {
            $result = [
                "Status" => false,
                "Message" => "Unidentified data type",
                "OriginData" => $r
            ];
        }
        Log::debug("Buy response: " . json_encode($result));


        return $result;
    }

    /**
     * @param $code
     * @param $qty
     * @param string $price
     * @return mixed
     */
    public static function sell($code, $qty, $price = "")
    {
        // TODO: Implement sell() method.

        $pt = $price ? $price : "market price";
        Log::debug("Sell out for stock: $code qty: {$qty} at {$pt}");

        $url = env("VENDOR_URL") . "FBS/order";
        $r = CURL::post($url, ["code" => $code, "qty" => $qty, "price" => $price, "bs" => "S"]);

        if (isset($r->Result->Data)) {
            $result = [
                "Status" => true,
                "Type" => "FBS",
                "CreateTime" => $r->Result->Data->Row->{"@CreateTime"},
                "OID" => $r->Result->Data->Row->{"@OID"},
                "OrderNo" => $r->Result->Data->Row->{"@OrderNo"},
                "OriginData" => $r
            ];
        } elseif (isset($r->Result->Error)) {
            $result = [
                "Status" => false,
                "Type" => "FBS",
                "Message" => "{$r->Result->Error->{"@Msg"}}",
                "OriginData" => $r
            ];
        } else {
            $result = [
                "Status" => false,
                "Type" => "FBS",
                "Message" => "Unidentified data type",
                "OriginData" => $r
            ];
        }

        Log::debug("Sell response: " . json_encode($result));


        return $result;
    }

    /**
     * @param $OID
     * @param $orderNo
     * @return mixed
     */
    public static function cancel($OID, $orderNo)
    {
        // TODO: Implement cancel() method.
        $url = env("VENDOR_URL") . "FBS/order";
        $r = CURL::delete($url, ["oid" => $OID, "orderNo" => $orderNo]);
        Log::debug("Cancel response: " . json_encode($r));

        return $r;
    }

    /**
     * @inheritDoc
     */
    public static function login()
    {
        // TODO: Implement login() method.
        return CURL::post(env("VENDOR_URL") . "FBS/login", ["username" => "TB", "password" => "Wwjfhwwrihefh"]);
    }

    /**
     * @inheritDoc
     */
    public static function logout()
    {
        // TODO: Implement logout() method.
        return CURL::get(env("VENDOR_URL") . "FBS/logout");
    }

    /**
     * @inheritDoc
     */
    public static function list(): array
    {
        // TODO: Implement list() method.
        $r =  CURL::get(env("VENDOR_URL") . "FBS/list");

        $result = [];
        if (isset($r->Result->Data->Row)) {
            $rows = $r->Result->Data->Row;
            foreach ($rows as $row) {
                $result[] = [
                    "OMID" => $row->{"@OMID"},
                    "OrderNo" => $row->{"@OrderNo"},
                    "CreateTime" => $row->{"@CreateTime"},
                    "BS" => $row->{"@BS"},
                    "StockID" => $row->{"@StockID"},
                    "Price" => $row->{"@Price"},
                    "Fee" => $row->{"@Fee"},
                    "Tax" => $row->{"@Tax"}
                ];
            }
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public static function all(): array
    {
        // TODO: Implement all() method.
        $r = CURL::get(env("VENDOR_URL") . "FBS/all");

        $result = [];
        if (isset($r->Result->Data->Row)) {
            $rows = $r->Result->Data->Row;
            foreach ($rows as $row) {
                $result[$row->{"@OrderNo"}] = [
                    "OID" => $row->{"@OID"},
                    "OrderNo" => $row->{"@OrderNo"},
                    "ServerTime" => $r->Result->{"@ServerTime"},
                    "CreateTime" => $row->{"@CreateTime"},
                    "ConfirmTime" => $row->{"@ConfirmTime"},
                    "UpdateTime" => $row->{"@UpdateTime"},
                    "BS" => $row->{"@BS"},
                    "StockID" => $row->{"@StockID"},
                    "Price" => $row->{"@Price"},
                    "AvgPrice" => $row->{"@AvgPrice"},
                    "State" => $row->{"@State"},
                    "StateMsg" => $row->{"@StateMsg"},
                    "CanCancel" => $row->{"@CanCancel"},
                    "CanModify" => $row->{"@CanModify"}
                ];
            }
        }

        return $result;
    }
}
