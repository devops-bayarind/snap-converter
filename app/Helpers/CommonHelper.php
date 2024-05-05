<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class CommonHelper
{

    public static function getCompCodeLength($serviceCode): int
    {
        switch ($serviceCode) {
            case "1042":
            case "1043":
            case "1074":
            case "1011":
                return 4;
            case "1062":
            case "1021":
                return 5;
            default:
                return 0;
        }

    }

    public static function isAmountFormat($data){
        return preg_match('/^([0]{1}|[1-9]{1}[0-9]{1,10})[.]{1}[0]{2}$/', $data);
    }

    public static  function isISO8601Date($value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        $dateTime = \DateTime::createFromFormat(\DateTime::ISO8601, $value);
        if ($dateTime) {
            $str = ($dateTime->format(\DateTime::ISO8601));
            $str = substr($str, 0, -2).':'.substr($value,-2);
            return $str === $value;
        }
        return false;
    }

    static function Log($message, $data = array()){
        Log::channel('snap')->info("[".SESSION_ID."] "." $message", $data);
    }

    static function decrypt($string, $salt)
    {
        $output = false;
        $encrypt_method = "AES-256-CBC";
        $secret_key = $salt . 'key';
        $secret_iv = $salt . 'iv';
        $key = hash('sha256', $secret_key);
        $iv = substr(hash('sha256', $secret_iv), 0, 16);
        $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
        return $output;
    }

    static function checkDateFormat( $date ){
        if($date == "0000-00-00 00:00:00") return false;
        return preg_match('/^(\d{4})-(\d\d)-(\d\d) (\d\d):(\d\d):(\d\d)$/',$date);
    }
}
