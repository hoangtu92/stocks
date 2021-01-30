<?php


namespace App\StockVendors;


interface Vendor
{

    /**
     * @param $code
     * @param $qty
     * @param string $price
     * @return mixed
     */
    public static function buy(string $code, int $qty, $price = "");

    /**
     * @param $code
     * @param $qty
     * @param string $price
     * @return mixed
     */
    public static function sell(string $code, int $qty, $price = "");

    /**
     * @param string $OID
     * @param string $orderNo
     * @return mixed
     */
    public static function cancel(string $OID, string $orderNo);

    /**
     * @return mixed
     */
    public static function login();

    /**
     * @return mixed
     */
    public static function logout();

    /**
     * @return mixed
     */
    public static function list();

    /**
     * @return mixed
     */
    public static function all();
}
