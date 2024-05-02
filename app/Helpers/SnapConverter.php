<?php

namespace App\Helpers;

use Illuminate\Http\Request;

class SnapConverter
{
    //region non snap to snap

    //create va
    static function convertRequestBodyCreateVaNonSnapToSnap(Request $request): array
    {
        $customerAccount = ($request->input("customerAccount") ?? "");
        $serviceCode = ($request->input("serviceCode") ?? "");
        $binLength = CommonHelper::getCompCodeLength($serviceCode);

        $partnerServiceId = "";
        $customerNo = "";
        $virtualAccountNo = "";
        if (strlen($customerAccount) > $binLength) {
            $partnerServiceId = str_pad(substr($customerAccount, 0, $binLength), 8, " ", STR_PAD_LEFT);
            $customerNo = substr($customerAccount, $binLength);
            $virtualAccountNo = $partnerServiceId . $customerNo;
        }
        return [
            "partnerServiceId" => $partnerServiceId,
            "customerNo" => $customerNo,
            "virtualAccountNo" => $virtualAccountNo,
            "virtualAccountName" => ($request->input("customerName") ?? ""),
            "virtualAccountEmail" => ($request->input("customerEmail") ?? ""),
            "virtualAccountPhone" => ($request->input("customerPhone") ?? ""),
            "trxId" => ($request->input("transactionNo") ?? ""),
            "totalAmount" => [
                "value" => intval(($request->input("transactionAmount") ?? "0")) . ".00",
                "currency" => "IDR"
            ],
            "billDetails" => [
                [
                    "indonesia" => ($request->input("description") ?? ""),
                    "english" => ($request->input("description") ?? "")
                ]
            ],
            "expiredDate" => (empty(($request->input("transactionExpire")))) ? '' : date('c', strtotime($request->input("transactionExpire"))),
        ];

    }

    //payment
    static function convertResponseBodyPaymentNonSnapToSnap($snapRequest, $nonSnapResponse)
    {
        $apiServiceCode = "25";
        if (($nonSnapResponse["paymentStatus"] ?? "01") == "00") {
            return [
                "responseCode" => "200" . $apiServiceCode . "00",
                "responseMessage" => "Success",
                "virtualAccountData" => [
                    "partnerServiceId" => $snapRequest["partnerServiceId"],
                    "customerNo" => $snapRequest["customerNo"],
                    "virtualAccountNo" => $snapRequest["virtualAccountNo"],
                    "virtualAccountName" => ($snapRequest["virtualAccountName"] ?? ""),
                    "paymentFlagStatus" => "00",
                    "paidAmount" => $snapRequest["paidAmount"],
                    "paymentRequestId" => ($snapRequest["paymentRequestId"] ?? ""),
                    "paymentFlagReason" => [
                        "Indonesia" => "Sukses",
                        "English" => "Success"
                    ]
                ]
            ];
        } else if (($nonSnapResponse["paymentStatus"] ?? "01") == "01") {
            if ((strtolower($nonSnapResponse["paymentMessage"] ?? "")) == "invalid transaction amount") {
                return [
                    "responseCode" => "404" . $apiServiceCode . "13",
                    "responseMessage" => "Invalid amount"
                ];
            }
            return [
                "responseCode" => "404" . $apiServiceCode . "12",
                "responseMessage" => "Bill not found"
            ];
        } else if (($nonSnapResponse["paymentStatus"] ?? "01") == "02") {
            return [
                "responseCode" => "404" . $apiServiceCode . "14",
                "responseMessage" => "Bill has been paid"
            ];
        } else if (($nonSnapResponse["paymentStatus"] ?? "01") == "04") {
            return [
                "responseCode" => "404" . $apiServiceCode . "19",
                "responseMessage" => "Bill expired"
            ];
        } else if (($nonSnapResponse["paymentStatus"] ?? "01") == "05") {
            return [
                "responseCode" => "404" . $apiServiceCode . "04",
                "responseMessage" => "Transaction cancelled"
            ];
        }
        return [
            "responseCode" => "404" . $apiServiceCode . "12",
            "responseMessage" => "Bill not found"
        ];
    }

    //inquiry
    static function convertResponseBodyInquiryNonSnapToSnap($snapRequest, $nonSnapResponse)
    {
        $apiServiceCode = "24";

        //NOTE: If there are mandatory fields on a non-Snap endpoint, but they are not filled in response, Snap will return a bill not found response
        if (
            empty(($nonSnapResponse["channelId"] ?? ""))
            || empty(($nonSnapResponse["currency"] ?? ""))
            || empty(($nonSnapResponse["transactionNo"] ?? ""))
            || empty(($nonSnapResponse["transactionDate"] ?? ""))
            || empty(($nonSnapResponse["transactionExpire"] ?? ""))
            || empty(($nonSnapResponse["description"] ?? ""))
            || empty(($nonSnapResponse["customerAccount"] ?? ""))
            || empty(($nonSnapResponse["customerName"] ?? ""))
        ) {
            return [
                "responseCode" => "404" . $apiServiceCode . "12",
                "responseMessage" => "Bill not found"
            ];
        } else {
            $snapResponse = [
                "responseCode" => "200" . $apiServiceCode . "00",
                "responseMessage" => "Success",
                "virtualAccountData" => [
                    "partnerServiceId" => $snapRequest["partnerServiceId"],
                    "customerNo" => $snapRequest["customerNo"],
                    "virtualAccountNo" => $snapRequest["virtualAccountNo"],
                    "virtualAccountName" => $nonSnapResponse["customerName"],
                    "virtualAccountEmail" => $nonSnapResponse["customerEmail"] ?? "",
                    "inquiryRequestId" => $snapRequest["inquiryRequestId"],
                    "totalAmount" => [
                        "value" => intval(($nonSnapResponse["transactionAmount"] ?? "0")) . ".00",
                        "currency" => $nonSnapResponse["currency"]
                    ],
                    "additionalInfo" => [
                        "trxId" => $nonSnapResponse["transactionNo"],
                        "expiredDate" => $nonSnapResponse["transactionExpire"]
                    ]
                ]
            ];
            return $snapResponse;
        }
    }

    //endregion non snap to snap


    //region snap to non snap

    //create va
    static function convertResponseBodyCreateVaSnapToNonSnap(Request $request, $snapResponse): array
    {
        if (($snapResponse["responseCode"] ?? "") == "2002700") {
            return [
                "channelId" => ($request->input("channelId") ?? ""),
                "currency" => $snapResponse["virtualAccountData"]["totalAmount"]["currency"],
                "insertStatus" => "00",
                "insertMessage" => "Success",
                "insertId" => $snapResponse["virtualAccountData"]["additionalInfo"]["insertId"],
                "additionalData" => ""
            ];
        }
        return [
            "channelId" => ($request->input("channelId") ?? ""),
            "currency" => "",
            "insertStatus" => "01",
            "insertMessage" => ($snapResponse["responseMessage"] ?? "Failed"),
            "additionalData" => ""
        ];

    }

    //payment
    static function convertRequestBodyPaymentSnapToNonSnap(Request $request): array
    {
        $snapParam = $request->all();
        $flagType = "11";
        if (($snapParam["flagAdvice"] ?? "") == "Y") {
            $flagType = "12";
        }
        $insertId = "";
        if (!empty(($snapParam["additionalInfo"] ?? ""))) {
            if (!empty(($snapParam["additionalInfo"]["insertId"]))) {
                $insertId = $snapParam["additionalInfo"]["insertId"];
            }
        }
        $nonSnapPaymentFlagParam = [
            "currency" => $snapParam["paidAmount"]["currency"],
            "transactionNo" => $snapParam["trxId"],
            "transactionAmount" => intval($snapParam["paidAmount"]["value"]),
            "transactionDate" => date('Y-m-d H:i:s', empty(($request["trxDateTime"] ?? "") ? time() : strtotime($request["trxDateTime"]))),
            "channelType" => ($snapParam["channelCode"] ?? ""),
            "transactionStatus" => "00",
            "transactionMessage" => "Approved",
            "customerAccount" => trim($snapParam["virtualAccountNo"]),
            "flagType" => $flagType,
            "insertId" => $insertId,
            "paymentReffId" => ($snapParam["referenceNo"] ?? ""),
            "channelId" => ($request->header("X-PARTNER-ID") ?? ""),
        ];
        //generate auth code
        $prepareAuthCode = $nonSnapPaymentFlagParam["transactionNo"]
            . $nonSnapPaymentFlagParam["transactionAmount"]
            . $nonSnapPaymentFlagParam["channelId"]
            . $nonSnapPaymentFlagParam["transactionStatus"]
            . $nonSnapPaymentFlagParam["insertId"]
            . env('BAYARIND_SECRET_KEY');
        $nonSnapPaymentFlagParam["authCode"] = hash('sha256', $prepareAuthCode);
        return $nonSnapPaymentFlagParam;
    }

    //inquiry
    static function convertRequestBodyInquirySnapToNonSnap(Request $request): array
    {
        $snapParam = $request->all();

        //default currency = IDR
        $currency = "IDR";
        if (!empty(($snapParam["additionalInfo"] ?? []))) {
            if (!empty(($snapParam["additionalInfo"]["currency"] ?? ""))) {
                $currency = $snapParam["additionalInfo"]["currency"];
            }
        }

        $nonSnapInquiryParam = [
            "currency" => $currency,
            "transactionDate" => date('Y-m-d H:i:s', empty(($request["trxDateInit"] ?? "") ? time() : strtotime($request["trxDateInit"]))),
            "channelType" => $snapParam["channelCode"] ?? "",
            "customerAccount" => trim($snapParam["virtualAccountNo"]),
            "inquiryReffId" => $snapParam["inquiryRequestId"],
        ];
        //generate auth code
        $prepareAuthCode = $nonSnapInquiryParam["customerAccount"]
            . $nonSnapInquiryParam["inquiryReffId"]
            . env('BAYARIND_SECRET_KEY');
        $nonSnapInquiryParam["authCode"] = hash('sha256', $prepareAuthCode);
        return $nonSnapInquiryParam;
    }

    //endregion snap to non snap
}
