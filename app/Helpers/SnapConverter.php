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

        $snapCreateVaRequestBody = [
            "partnerServiceId" => $partnerServiceId,
            "customerNo" => $customerNo,
            "virtualAccountNo" => $virtualAccountNo,
            "virtualAccountName" => ($request->input("customerName") ?? ""),
            "virtualAccountEmail" => ($request->input("customerEmail") ?? ""),
            "virtualAccountPhone" => ($request->input("customerPhone") ?? ""),
            "trxId" => ($request->input("transactionNo") ?? ""),
            "totalAmount" => [
                "value" => intval(($request->input("transactionAmount") ?? "0")) . ".00",
                "currency" => ($request->input("currency") ?? ""),
            ],
            "billDetails" => [
                [
                    "indonesia" => ($request->input("description") ?? ""),
                    "english" => ($request->input("description") ?? "")
                ]
            ],
            "expiredDate" => (empty(($request->input("transactionExpire") ?? ""))) ? '' : date('c', strtotime($request->input("transactionExpire"))),
        ];

        $jsonFreeText = json_decode($request->input('freeTexts') ?? "");
        if ($jsonFreeText) {
            $snapCreateVaRequestBody["additionalInfo"]["freeTexts"] = $jsonFreeText;
        }

        $jsonItemDetails = json_decode($request->input('itemDetails') ?? "");
        if ($jsonItemDetails) {
            $snapCreateVaRequestBody["additionalInfo"]["itemDetails"] = $jsonItemDetails;
        }
        return $snapCreateVaRequestBody;
    }

    //inquiry status va
    static function convertRequestBodyInquiryStatusVaNonSnapToSnap(Request $request): array
    {
        //[NOTE]: please add customerAccount in non snap parameter
        $customerAccount = ($request->input("customerAccount") ?? "");

        //[NOTE]: please add serviceCode in non snap parameter
        $serviceCode = ($request->input("serviceCode") ?? "");

        //[NOTE]: if your non snap not send requestId, converter will be generate this parameter
        if (!empty(($request->input("requestId") ?? ""))) {
            $inquiryRequestId = $request->input("requestId");
        } else {
            $inquiryRequestId = uniqid(time());
        }

        $binLength = CommonHelper::getCompCodeLength($serviceCode);

        $partnerServiceId = "";
        $customerNo = "";
        $virtualAccountNo = "";
        if (strlen($customerAccount) > $binLength && $binLength > 0) {
            $partnerServiceId = str_pad(substr($customerAccount, 0, $binLength), 8, " ", STR_PAD_LEFT);
            $customerNo = substr($customerAccount, $binLength);
            $virtualAccountNo = $partnerServiceId . $customerNo;
        }

        //[NOTE] if non snap not send Customer Account, converter will generate dummy virtual account, because this parameter is required for snap
        if ($binLength > 0 && (empty($virtualAccountNo) || empty($customerNo) || empty($partnerServiceId))) {
            $randNumber = substr(str_shuffle("0123456789"), 0, $binLength);
            $partnerServiceId = str_pad($randNumber, 8, " ", STR_PAD_LEFT);
            $customerNo = str_pad(rand(0, pow(10, 16) - 1), 16, '0', STR_PAD_LEFT);
            $virtualAccountNo = $partnerServiceId . $customerNo;
        }

        $snapInquiryRequestBody = [
            "partnerServiceId" => $partnerServiceId,
            "customerNo" => $customerNo,
            "virtualAccountNo" => $virtualAccountNo,
            "inquiryRequestId" => $inquiryRequestId,
        ];

        if (!empty(($request->input("queryRequest") ?? ""))) {
            $jsonQueryRequest = json_decode($request->input("queryRequest"), true);
            if ($jsonQueryRequest) {
                if (isset($jsonQueryRequest[0])) {
                    $snapInquiryRequestBody["additionalInfo"] = [
                        "trxId" => ($jsonQueryRequest[0]["transactionNo"] ?? ""),
                        "trxDateInit" => empty(($jsonQueryRequest[0]["transactionDate"] ?? "")) ? "" : date('c', strtotime($jsonQueryRequest[0]["transactionDate"]))
                    ];

                    // multipleQueryRequest
                    if (count($jsonQueryRequest) > 1) {
                        $listQueryRequest = [];
                        foreach ($jsonQueryRequest as $itemQueryRequest) {
                            $listQueryRequest[] = [
                                "trxId" => ($itemQueryRequest["transactionNo"] ?? ""),
                                "trxDateInit" => empty(($itemQueryRequest["transactionDate"] ?? "")) ? "" : date('c', strtotime($itemQueryRequest["transactionDate"]))
                            ];
                        }
                        $snapInquiryRequestBody["additionalInfo"]["queryRequest"] = $listQueryRequest;
                    }

                }

            }
        }

        return $snapInquiryRequestBody;

    }

    //void va
    static function convertRequestBodyVoidVaNonSnapToSnap(Request $request): array
    {
        //[NOTE]: please add customerAccount in non snap parameter
        $customerAccount = ($request->input("customerAccount") ?? "");

        //[NOTE]: please add serviceCode in non snap parameter
        $serviceCode = ($request->input("serviceCode") ?? "");

        $binLength = CommonHelper::getCompCodeLength($serviceCode);

        $partnerServiceId = "";
        $customerNo = "";
        $virtualAccountNo = "";
        if (strlen($customerAccount) > $binLength && $binLength > 0) {
            $partnerServiceId = str_pad(substr($customerAccount, 0, $binLength), 8, " ", STR_PAD_LEFT);
            $customerNo = substr($customerAccount, $binLength);
            $virtualAccountNo = $partnerServiceId . $customerNo;
        }

        //[NOTE] if non snap not send Customer Account, converter will generate dummy virtual account, because this parameter is required for snap
        if ($binLength > 0 && (empty($virtualAccountNo) || empty($customerNo) || empty($partnerServiceId))) {
            $randNumber = substr(str_shuffle("0123456789"), 0, $binLength);
            $partnerServiceId = str_pad($randNumber, 8, " ", STR_PAD_LEFT);
            $customerNo = str_pad(rand(0, pow(10, 16) - 1), 16, '0', STR_PAD_LEFT);
            $virtualAccountNo = $partnerServiceId . $customerNo;
        }

        return [
            "partnerServiceId" => $partnerServiceId,
            "customerNo" => $customerNo,
            "virtualAccountNo" => $virtualAccountNo,
            "trxId" => ($request->input("transactionNo") ?? ""),
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

    //response create va
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
        } else if (($snapResponse["responseCode"] ?? "") == "4092701") {
            return [
                "channelId" => ($request->input("channelId") ?? ""),
                "currency" => "",
                "insertStatus" => "01",
                "insertMessage" => "Transaction is Exist",
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

    //response inquiry status va
    static function convertResponseBodyInquiryStatusVaSnapToNonSnap(Request $request, $snapResponse): array
    {
        //prepare query response non snap
        $transactionNo = "";
        $transactionDate = "";
        $transactionStatus = "04";
        $transactionAmount = null;
        $transactionMessage = "";
        $insertId = "";
        $paymentDate = "";
        $inquiryReqId = "";
        $paymentReqId = "";


        $jsonQueryRequest = json_decode($request->input("queryRequest"), true);
        if ($jsonQueryRequest) {
            if (isset($jsonQueryRequest[0])) {
                $transactionNo = ($jsonQueryRequest[0]["transactionNo"] ?? "");
                $transactionDate = ($jsonQueryRequest[0]["transactionDate"] ?? "");
            }
        }


        //transaction not found
        if (($snapResponse["responseCode"] ?? "") == "4042601") {
            $transactionMessage = "Transaction not found";
            $transactionStatus = "02";
            return [
                "channelId" => ($request->input("channelId") ?? ""),
                "queryResponse" => [
                    [
                        "transactionNo" => $transactionNo,
                        "transactionAmount" => $transactionAmount,
                        "transactionDate" => $transactionDate,
                        "transactionStatus" => $transactionStatus,
                        "transactionMessage" => $transactionMessage,
                        "paymentDate" => $paymentDate,
                        "insertId" => $insertId,
                        "inquiryReqId" => $inquiryReqId,
                        "paymentReqId" => $paymentReqId
                    ]
                ],
            ];
        } else if (($snapResponse["responseCode"] ?? "") == "2002600") {
            if (count(($snapResponse["virtualAccountData"]["additionalInfo"]["list"] ?? [])) > 1) {
                $queryResponse = [];
                foreach ($snapResponse["virtualAccountData"]["additionalInfo"]["list"] as $itemQueryStatus) {
                    $queryResponse[]=self::convertQueryFromSnapToNonSnap(
                        $itemQueryStatus,
                        $itemQueryStatus["trxId"],
                        $itemQueryStatus["trxStatus"],
                        $itemQueryStatus["insertId"],
                    );
                }
            } else {
                $queryResponse = [
                    self::convertQueryFromSnapToNonSnap(
                        $snapResponse["virtualAccountData"],
                        $transactionNo,
                        $snapResponse["virtualAccountData"]["additionalInfo"]["trxStatus"],
                        ($jsonQueryRequest[0]["transactionNo"] ?? "")
                    )
                ];
            }
            return [
                "channelId" => ($request->input("channelId") ?? ""),
                "queryResponse" => $queryResponse
            ];

        } else if (($snapResponse["responseCode"] ?? "") == "4012600") {
            return [
                "channelId" => ($request->input("channelId") ?? ""),
                "queryResponse" => "Technical Problem [Unauthorized]"
            ];
        } else {
            return [
                "channelId" => ($request->input("channelId") ?? ""),
                "queryResponse" => "Technical Problem"
            ];
        }


    }

    static function convertQueryFromSnapToNonSnap($snapQueryResponse, $transactionNo, $snapTrxStatus, $insertId)
    {
        $transactionAmount = intval($snapQueryResponse["totalAmount"]["value"]) . "";
        $transactionDate = empty(($snapQueryResponse["transactionDate"] ?? "")) ? "" : date('Y-m-d H:i:s', strtotime($snapQueryResponse["transactionDate"]));
//        $insertId = $snapQueryResponse["additionalInfo"]["insertId"];
        $paymentDate = empty(($snapQueryResponse["trxDateTime"] ?? "")) ? "" : date('Y-m-d H:i:s', strtotime($snapQueryResponse["trxDateTime"]));
        $inquiryReqId = $snapQueryResponse["inquiryRequestId"];
        $paymentReqId = $snapQueryResponse["paymentRequestId"];
        //pending payment
//        if ($snapResponse["virtualAccountData"]["additionalInfo"]["trxStatus"] == "00") {
//            $transactionStatus = "00";
//            $transactionMessage = "Success";
//        } else if ($snapResponse["virtualAccountData"]["additionalInfo"]["trxStatus"] == "03") {
//            $transactionStatus = "03";
//            $transactionMessage = "There is no payment in this transaction";
//        } else if ($snapResponse["virtualAccountData"]["additionalInfo"]["trxStatus"] == "06") {
//            $transactionStatus = "04";
//            $transactionMessage = "Technical Problem";
//        }
        $transactionStatus = "04";
        $transactionMessage = "Technical Problem";
        if ($snapTrxStatus == "00") {
            $transactionStatus = "00";
            $transactionMessage = "Success";
        } else if ($snapTrxStatus == "03") {
            $transactionStatus = "03";
            $transactionMessage = "There is no payment in this transaction";
        } else if ($snapTrxStatus == "06") {
            $transactionStatus = "04";
            $transactionMessage = "Technical Problem";
        } else if ($snapTrxStatus == "07") {
            $transactionStatus = "02";
            $transactionMessage = "Transaction Not Found";
        }
        return [
            "transactionNo" => $transactionNo,
            "transactionAmount" => $transactionAmount,
            "transactionDate" => $transactionDate,
            "transactionStatus" => $transactionStatus,
            "transactionMessage" => $transactionMessage,
            "paymentDate" => $paymentDate,
            "insertId" => $insertId,
            "inquiryReqId" => $inquiryReqId,
            "paymentReqId" => $paymentReqId
        ];
    }

    //response void  va
    static function convertResponseBodyVoidVaSnapToNonSnap(Request $request, $snapResponse): array
    {
        $transactionNo = ($request->input("transactionNo") ?? "");
        $transactionMessage = "General Error";
        $transactionAmount = "0";
        $transactionStatus = "01";

        //transaction not found
        if (($snapResponse["responseCode"] ?? "") == "2003100") {
            $transactionMessage = "Success";
            $transactionStatus = "00";
        } else if (($snapResponse["responseCode"] ?? "") == "4043101") {
            $transactionMessage = "Transaction Not Found";
        } else if (($snapResponse["responseCode"] ?? "") == "4043104") {
            $transactionMessage = "Transaction Has Been Canceled";
        } else if (($snapResponse["responseCode"] ?? "") == "4043114") {
            $transactionMessage = "Transaction Has Been Paid";
        }
        return [
            "channelId" => ($request->input("channelId") ?? ""),
            "transactionNo" => $transactionNo,
            "transactionAmount" => $transactionAmount,
            "transactionStatus" => $transactionStatus,
            "transactionMessage" => $transactionMessage,
            "transactionType" => "VOID INSERT",
        ];

    }

    //request body payment
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

    //request bofy inquiry
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
