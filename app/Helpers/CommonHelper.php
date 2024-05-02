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
}
