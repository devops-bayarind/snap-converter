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

        $description = ($request->input("description") ?? "");
        $shortDescription = (strlen($description) > 18) ? substr($description, 0, 18) : $description;

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
                    "billDescription" => [
                        "indonesia" => $shortDescription,
                        "english" => $shortDescription
                    ]
                ]
            ],
            "expiredDate" => (empty(($request->input("transactionExpire") ?? ""))) ? '' : date('c', strtotime($request->input("transactionExpire"))),
            "additionalInfo" => [
                "passApp" => strtolower(hash('sha256', env('BAYARIND_SECRET_KEY')))
            ]
        ];

        if ($request->has("freeTexts")) {
            if (is_array($request->input('freeTexts'))) {
                $snapCreateVaRequestBody["additionalInfo"]["freeTexts"] = $request->input('freeTexts');
            } else if (is_string($request->input('freeTexts'))) {
                $jsonFreeText = json_decode($request->input('freeTexts') ?? "");
                if ($jsonFreeText) {
                    $snapCreateVaRequestBody["additionalInfo"]["freeTexts"] = $jsonFreeText;
                }
            }
        }
        if (strlen($description) > 18) {
            $snapCreateVaRequestBody["additionalInfo"]["billDescription"] = [
                "indonesia" => $description,
                "english" => $description
            ];
        }

        if ($request->has("itemDetails")) {
            if (is_array($request->input('itemDetails'))) {
                $snapCreateVaRequestBody["additionalInfo"]["itemDetails"] = $request->input('itemDetails');
            } else if (is_string($request->input('itemDetails'))) {
                $jsonItemDetails = json_decode($request->input('itemDetails') ?? "");
                if ($jsonItemDetails) {
                    $snapCreateVaRequestBody["additionalInfo"]["itemDetails"] = $jsonItemDetails;
                }
            }
        }

        if ($request->has("additionalData")) {
            if (is_string($request->input('additionalData'))) {
                if (!empty($request->input('additionalData'))) {
                    $snapCreateVaRequestBody["additionalInfo"]["additionalData"] = $request->input('additionalData');
                }
            }
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
            $randNumber = substr(str_shuffle("123456789"), 0, $binLength);
            $partnerServiceId = str_pad($randNumber, 8, " ", STR_PAD_LEFT);
            $customerNoLength = 11;
            if ($serviceCode == "1074") {
                $customerNoLength = 12;
            }
            $customerNo = str_pad(rand(0, pow(10, $customerNoLength) - 1), $customerNoLength, '0', STR_PAD_LEFT);
            $virtualAccountNo = $partnerServiceId . $customerNo;
        }

        $snapInquiryRequestBody = [
            "partnerServiceId" => $partnerServiceId,
            "customerNo" => $customerNo,
            "virtualAccountNo" => $virtualAccountNo,
            "inquiryRequestId" => $inquiryRequestId,
        ];

        $jsonQueryRequest = $request->input("queryRequest");
        if (!is_array($jsonQueryRequest)) {
            if (!!json_encode($jsonQueryRequest, true)) {
                $jsonQueryRequest = json_decode($jsonQueryRequest, true);
            }
        }

        if (is_array($jsonQueryRequest)) {
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

        $snapInquiryRequestBody["additionalInfo"]["passApp"] = strtolower(hash('sha256', env('BAYARIND_SECRET_KEY')));

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
            $randNumber = substr(str_shuffle("123456789"), 0, $binLength);
            $partnerServiceId = str_pad($randNumber, 8, " ", STR_PAD_LEFT);
            $customerNoLength = 11;
            if ($serviceCode == "1074") {
                $customerNoLength = 12;
            }
            $customerNo = str_pad(rand(0, pow(10, $customerNoLength) - 1), $customerNoLength, '0', STR_PAD_LEFT);
            $virtualAccountNo = $partnerServiceId . $customerNo;
        }

        return [
            "partnerServiceId" => $partnerServiceId,
            "customerNo" => $customerNo,
            "virtualAccountNo" => $virtualAccountNo,
            "trxId" => ($request->input("transactionNo") ?? ""),
            "additionalInfo" => [
                "passApp" => strtolower(hash('sha256', env('BAYARIND_SECRET_KEY')))
            ]
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
                "responseMessage" => "Bill not found [" . ($nonSnapResponse["paymentMessage"] ?? "") . "]"
            ];
        } else if (($nonSnapResponse["paymentStatus"] ?? "01") == "02") {
            return [
                "responseCode" => "404" . $apiServiceCode . "14",
                "responseMessage" => "Bill has been paid"
            ];
        } else if (($nonSnapResponse["paymentStatus"] ?? "01") == "03") {
            return [
                "responseCode" => "400" . $apiServiceCode . "01",
                "responseMessage" => ($nonSnapResponse["paymentMessage"] ?? "Invalid parameter")
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

            $description =  ($nonSnapResponse["description"] ?? "");
            $shortDescription = (strlen($description) > 18) ? substr($description, 0, 18) : $description;


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
                    "billDetails" => [
                        [
                            "billDescription" => [
                                "indonesia" => $shortDescription,
                                "english" => $shortDescription
                            ]
                        ]
                    ],
                    "additionalInfo" => [
                        "trxId" => $nonSnapResponse["transactionNo"],
                        "expiredDate" => date('c', strtotime($nonSnapResponse["transactionExpire"]))
                    ]
                ]
            ];
            if (strlen($description) > 18) {
                $snapResponse["virtualAccountData"]["additionalInfo"]["billDescription"] = [
                    "indonesia" => $description,
                    "english" => $description
                ];
            }
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
        } else if (($snapResponse["responseCode"] ?? "") == "4042716") {
            return [
                "channelId" => ($request->input("channelId") ?? ""),
                "currency" => "",
                "insertStatus" => "01",
                "insertMessage" => "Invalid Company Code",
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
        $transactionAmount = null;


        $jsonQueryRequest = $request->input("queryRequest");
        if (!is_array($jsonQueryRequest)) {
            if (!!json_encode($jsonQueryRequest, true)) {
                $jsonQueryRequest = json_decode($jsonQueryRequest, true);
            }
        }
        if (is_array($jsonQueryRequest)) {
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
                    ]
                ],
            ];
        } else if (($snapResponse["responseCode"] ?? "") == "2002600") {
            if (count(($snapResponse["additionalInfo"]["list"] ?? [])) > 1) {
                $queryResponse = [];
                foreach ($snapResponse["additionalInfo"]["list"] as $itemQueryStatus) {
                    $queryResponse[] = self::convertQueryFromSnapToNonSnap(
                        $itemQueryStatus,
                        $itemQueryStatus["trxId"],
                        $itemQueryStatus["trxStatus"]
                    );
                }
            } else {
                $queryResponse = [
                    self::convertQueryFromSnapToNonSnap(
                        $snapResponse["virtualAccountData"],
                        $transactionNo,
                        $snapResponse["additionalInfo"]["trxStatus"],
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

    static function convertQueryFromSnapToNonSnap($snapQueryResponse, $transactionNo, $snapTrxStatus)
    {
        $transactionAmount = intval($snapQueryResponse["totalAmount"]["value"]) . "";
        $transactionDate = empty(($snapQueryResponse["transactionDate"] ?? "")) ? "" : date('Y-m-d H:i:s', strtotime($snapQueryResponse["transactionDate"]));

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
            $transactionMessage = "Transaction not found";
        }
        return [
            "transactionNo" => $transactionNo,
            "transactionAmount" => $transactionAmount,
            "transactionDate" => $transactionDate,
            "transactionStatus" => $transactionStatus,
            "transactionMessage" => $transactionMessage,
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

        if (isset($snapParam["additionalInfo"])) {
            if (!empty(($snapParam["additionalInfo"]["flagType"] ?? ""))) {
                $flagType = $snapParam["additionalInfo"]["flagType"];
            }
        }


        $insertId = "";
        if (!empty(($snapParam["additionalInfo"] ?? ""))) {
            if (!empty(($snapParam["additionalInfo"]["insertId"]))) {
                $insertId = $snapParam["additionalInfo"]["insertId"];
            }
        }
        $nonSnapPaymentFlagParam = [
            "channelId" => ($request->header("X-PARTNER-ID") ?? ""),
            "currency" => $snapParam["paidAmount"]["currency"],
            "transactionNo" => $snapParam["trxId"],
            "transactionAmount" => intval($snapParam["paidAmount"]["value"]),
            "transactionDate" => empty($snapParam["trxDateTime"] ?? "") ? "" : date('Y-m-d H:i:s', strtotime($snapParam["trxDateTime"])),
            "channelType" => ($snapParam["channelCode"] ?? ""),
            "transactionStatus" => "00",
            "transactionMessage" => "Approved",
            "customerAccount" => trim($snapParam["virtualAccountNo"]),
            "flagType" => $flagType,
            "insertId" => $insertId,
            "paymentReffId" => ($snapParam["referenceNo"] ?? ""),
            "additionalData" => ""
        ];

        if (isset($snapParam["additionalInfo"]["additionalData"])) {
            if (is_string($snapParam["additionalInfo"]["additionalData"])) {
                if (!empty($snapParam["additionalInfo"]["additionalData"])) {
                    $nonSnapPaymentFlagParam["additionalData"] = $snapParam["additionalInfo"]["additionalData"];
                }
            }
        }

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

    //request body inquiry
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
            "transactionDate" => empty($snapParam["trxDateInit"] ?? "") ? "" : date('Y-m-d H:i:s', strtotime($snapParam["trxDateInit"])),
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
