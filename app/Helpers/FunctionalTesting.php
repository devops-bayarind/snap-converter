<?php

namespace App\Helpers;

class FunctionalTesting
{
    static function mockUpSnapResponse($customerNo, $apiServiceCode){
        //mockup response conflict external id
        $externalIdMockupList = config("functionaltest.conflict_external_id");
        if (!empty($externalIdMockupList)){
            if (in_array($customerNo, $externalIdMockupList)){
                return [
                    "responseCode"=>  "409" . $apiServiceCode . "00",
                    "responseMessage" => "Conflict [X-EXTERNAL-ID]"
                ];
            }
        }

        //mockup response bill already paid
        $paidBillMockupList = config("functionaltest.paid_bill");
        if (!empty($paidBillMockupList)){
            if (in_array($customerNo, $paidBillMockupList)){
                return [
                    "responseCode"=>  "404" . $apiServiceCode . "14",
                    "responseMessage" => "Bill has been paid"
                ];
            }
        }

        //mockup response expired bill
        $expiredBillMockupList = config("functionaltest.expired_bill");
        if (!empty($expiredBillMockupList)){
            if (in_array($customerNo, $expiredBillMockupList)){
                return [
                    "responseCode"=>  "404" . $apiServiceCode . "19",
                    "responseMessage" => "Invalid Bill/Virtual Account [Expired Bill]"
                ];
            }
        }

        //mockup response invalid amount
        $invalidAmountMockupList = config("functionaltest.invalid_amount");
        if (!empty($invalidAmountMockupList)){
            if (in_array($customerNo, $invalidAmountMockupList)){
                return [
                    "responseCode"=>  "404" . $apiServiceCode . "13",
                    "responseMessage" => "Invalid Amount"
                ];
            }
        }

        return null;
    }
}
