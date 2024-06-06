<?php

namespace App\Http\Controllers;

use App\Helpers\CommonHelper;
use App\Helpers\FunctionalTesting;
use App\Helpers\SnapConverter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SnapToNonSnapController extends Controller
{

    public function payment(Request $request)
    {
        $apiServiceCode = "25";
        $snapRequestBody = $request->all();
        $validatePaymentRequest = $this->validatePaymentRequest($request);
        if (!is_null($validatePaymentRequest)) {
            return $validatePaymentRequest;
        }

        if (env("APP_ENV", "") != "production") {
            $mockUpSnapResponse = FunctionalTesting::mockUpSnapResponse($snapRequestBody["customerNo"], $apiServiceCode);
            if (!is_null($mockUpSnapResponse)) {
                return response()->json($mockUpSnapResponse, 200, ['X-TIMESTAMP' => date('c')]);
            }
        }

        //start payment to non snap
        $nonSnapUrlPayment = "";
        if (!empty(($snapRequestBody["additionalInfo"]["idApp"] ?? ""))) {
            $paymentFlag = CommonHelper::decrypt($snapRequestBody["additionalInfo"]["idApp"], env('BAYARIND_SECRET_KEY'));
            if ($paymentFlag) {
                $nonSnapUrlPayment = urldecode($paymentFlag);
            } else {
                CommonHelper::Log("Failed decrypt non snap url payment");
            }
        }
        CommonHelper::Log("Non Snap Payment URL: " . $nonSnapUrlPayment);
        if (empty($nonSnapUrlPayment)) {
            CommonHelper::Log("Empty non snap url payment");
            return response()->json([
                "responseCode" => "404" . $apiServiceCode . "02",
                "responseMessage" => "Invalid Routing"
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }

        //convert incoming request body(SNAP) to Non Snap
        $nonSnapRequestBody = SnapConverter::convertRequestBodyPaymentSnapToNonSnap($request);
        CommonHelper::Log("Non Snap Payment Request Form: " . json_encode($nonSnapRequestBody, JSON_UNESCAPED_SLASHES));

        try {
            $response = Http::asForm()->post($nonSnapUrlPayment, $nonSnapRequestBody);
            CommonHelper::Log("Non Snap Payment Response: " . $response);
            if ($response->successful()) {
                $nonSnapResponse = $response->json();

                //convert non snap response to snap response
                $snapResponse = SnapConverter::convertResponseBodyPaymentNonSnapToSnap($snapRequestBody, $nonSnapResponse);
                return response()->json($snapResponse, 200, ['X-TIMESTAMP' => date('c')]);
            } else {
                return response()->json([
                    "responseCode" => "500" . $apiServiceCode . "02",
                    "responseMessage" => "External Server Error"
                ], 200, ['X-TIMESTAMP' => date('c')]);
            }
        } catch (\Exception $exception) {
            CommonHelper::Log("Non Snap Payment Response Exception: " . $exception->getMessage());
            return response()->json([
                "responseCode" => "500" . $apiServiceCode . "02",
                "responseMessage" => "External Server Error"
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }

    }

    public function inquiry(Request $request)
    {
        $apiServiceCode = "24";
        $snapRequestBody = $request->all();
        $validateInquiryRequest = $this->validateInquiryRequest($request);
        if (!is_null($validateInquiryRequest)) {
            return $validateInquiryRequest;
        }

        if (env("APP_ENV", "") != "production") {
            $mockUpSnapResponse = FunctionalTesting::mockUpSnapResponse($snapRequestBody["customerNo"], $apiServiceCode);
            if (!is_null($mockUpSnapResponse)) {
                return response()->json($mockUpSnapResponse, 200, ['X-TIMESTAMP' => date('c')]);
            }
        }

        //start inquiry to non snap

        $nonSnapUrlInquiry = "";
        if (!empty(($snapRequestBody["additionalInfo"]["idApp"] ?? ""))) {
            $paymentFlag = CommonHelper::decrypt($snapRequestBody["additionalInfo"]["idApp"], env('BAYARIND_SECRET_KEY'));
            if ($paymentFlag) {
                $nonSnapUrlInquiry = urldecode($paymentFlag);
            } else {
                CommonHelper::Log("Failed decrypt non snap url inquiry");
            }
        }
        CommonHelper::Log("Non Snap Inquiry URL: " . $nonSnapUrlInquiry);
        if (empty($nonSnapUrlInquiry)) {
            CommonHelper::Log("Empty non snap url inquiry");
            return response()->json([
                "responseCode" => "404" . $apiServiceCode . "02",
                "responseMessage" => "Invalid Routing"
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }


        //convert incoming request body(SNAP) to Non Snap
        $nonSnapRequestBody = SnapConverter::convertRequestBodyInquirySnapToNonSnap($request);
        CommonHelper::Log("Non Snap Inquiry Request Form: " . json_encode($nonSnapRequestBody, JSON_UNESCAPED_SLASHES));


        try {
            $response = Http::withOptions([
                'http_errors' => false,
            ])->asForm()->post($nonSnapUrlInquiry, $nonSnapRequestBody);
            CommonHelper::Log("Non Snap Inquiry Response: " . $response->body());
            if ($response->successful()) {
                $nonSnapResponse = $response->json();

                //convert non snap response to snap response
                $snapResponse = SnapConverter::convertResponseBodyInquiryNonSnapToSnap($snapRequestBody, $nonSnapResponse);
                return response()->json($snapResponse, 200, ['X-TIMESTAMP' => date('c')]);
            } else {
                return response()->json([
                    "responseCode" => "404" . $apiServiceCode . "12",
                    "responseMessage" => "Bill not found"
                ], 200, ['X-TIMESTAMP' => date('c')]);
            }
        } catch (\Exception $exception) {
            CommonHelper::Log("Non Snap Inquiry Response Exception: " . $exception->getMessage());
            return response()->json([
                "responseCode" => "500" . $apiServiceCode . "02",
                "responseMessage" => "External Server Error"
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }

    }

    //region validator

    //payment validator
    private function validatePaymentRequest(Request $request): ?\Illuminate\Http\JsonResponse
    {
        $apiServiceCode = "25";
        // validate partnerServiceId
        $reqBody = $request->all();

        if (empty($reqBody["additionalInfo"]["passApp"])) {
            return response()->json([
                "responseCode" => "401" . $apiServiceCode . "00",
                "responseMessage" => "Unauthorized. Client Forbidden Access API",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }

        if (strtolower(hash('sha256', env('BAYARIND_SECRET_KEY'))) != $reqBody["additionalInfo"]["passApp"]) {
            return response()->json([
                "responseCode" => "401" . $apiServiceCode . "00",
                "responseMessage" => "Unauthorized. Client Forbidden Access API",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }

        if (empty(($reqBody["partnerServiceId"] ?? ""))) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "02",
                "responseMessage" => "Missing mandatory field partnerServiceId",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }
        if (strlen($request["partnerServiceId"]) != 8) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "01",
                "responseMessage" => "Invalid field format partnerServiceId length",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }

        if (!preg_match('/^\d+$/', trim($request["partnerServiceId"]))) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "01",
                "responseMessage" => "Invalid field format partnerServiceId",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }

        // validate customerNo
        if (empty(($request['customerNo'] ?? ""))) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "02",
                "responseMessage" => "Missing mandatory field customerNo",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }

        if (strlen($request["customerNo"]) > 20) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "01",
                "responseMessage" => "Invalid field format customerNo length",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }
        if (!preg_match('/^\d+$/', trim($request["customerNo"]))) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "01",
                "responseMessage" => "Invalid field format customerNo",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }

        //validate virtualAccountNo
        if (empty(($request['virtualAccountNo'] ?? ""))) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "02",
                "responseMessage" => "Missing mandatory field virtualAccountNo",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }

        if ($request["virtualAccountNo"] != $request["partnerServiceId"] . $request["customerNo"]) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "01",
                "responseMessage" => "Invalid field format virtualAccountNo",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }

        //validate paymentRequestId
        if (empty(($request['paymentRequestId'] ?? ""))) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "02",
                "responseMessage" => "Missing mandatory field paymentRequestId",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }

        //validate trxId
        if (empty(($request['trxId'] ?? ""))) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "02",
                "responseMessage" => "Missing mandatory field trxId",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }

        //validate paidAmount
        if (empty(($request['paidAmount'] ?? []))) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "02",
                "responseMessage" => "Missing mandatory field paidAmount",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }

        if (!is_array($request['paidAmount'])) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "01",
                "responseMessage" => "Invalid field format paidAmount",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }
        //validate paidAmount.value
        if (empty(($request['paidAmount']['value'] ?? ""))) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "02",
                "responseMessage" => "Missing mandatory field paidAmount.value",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }

        if (!CommonHelper::isAmountFormat($request['paidAmount']["value"])) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "01",
                "responseMessage" => "Invalid field format paidAmount.value",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }

        //validate paidAmount.currency
        if (empty(($request['paidAmount']['currency'] ?? ""))) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "02",
                "responseMessage" => "Missing mandatory field paidAmount.currency",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }

        if ($request['paidAmount']["currency"] != "IDR") {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "01",
                "responseMessage" => "Invalid field format paidAmount.currency",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }


        return null;
    }

    //inquiry validator
    private function validateInquiryRequest(Request $request): ?\Illuminate\Http\JsonResponse
    {
        $apiServiceCode = "24";
        // validate partnerServiceId
        $reqBody = $request->all();

        if (empty($reqBody["additionalInfo"]["passApp"])) {
            return response()->json([
                "responseCode" => "401" . $apiServiceCode . "00",
                "responseMessage" => "Unauthorized. Client Forbidden Access API",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }

        if (strtolower(hash('sha256', env('BAYARIND_SECRET_KEY'))) != $reqBody["additionalInfo"]["passApp"]) {
            return response()->json([
                "responseCode" => "401" . $apiServiceCode . "00",
                "responseMessage" => "Unauthorized. Client Forbidden Access API",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }

        if (empty(($reqBody["partnerServiceId"] ?? ""))) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "02",
                "responseMessage" => "Missing mandatory field partnerServiceId",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }
        if (strlen($request["partnerServiceId"]) != 8) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "01",
                "responseMessage" => "Invalid field format partnerServiceId length",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }

        if (!preg_match('/^\d+$/', trim($request["partnerServiceId"]))) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "01",
                "responseMessage" => "Invalid field format partnerServiceId",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }

        // validate customerNo
        if (empty(($request['customerNo'] ?? ""))) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "02",
                "responseMessage" => "Missing mandatory field customerNo",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }

        if (strlen($request["customerNo"]) > 20) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "01",
                "responseMessage" => "Invalid field format customerNo length",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }
        if (!preg_match('/^\d+$/', trim($request["customerNo"]))) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "01",
                "responseMessage" => "Invalid field format customerNo",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }

        //validate virtualAccountNo
        if (empty(($request['virtualAccountNo'] ?? ""))) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "02",
                "responseMessage" => "Missing mandatory field virtualAccountNo",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }

        if ($request["virtualAccountNo"] != $request["partnerServiceId"] . $request["customerNo"]) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "01",
                "responseMessage" => "Invalid field format virtualAccountNo",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }

        //validate inquiryRequestId
        if (empty(($request['inquiryRequestId'] ?? ""))) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "02",
                "responseMessage" => "Missing mandatory field inquiryRequestId",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }

        //validate trxDateInit
        if (empty(($request['trxDateInit'] ?? ""))) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "02",
                "responseMessage" => "Missing mandatory field trxDateInit",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }

        if (!CommonHelper::isISO8601Date($request['trxDateInit'])) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "01",
                "responseMessage" => "Invalid field format trxDateInit",
            ], 200, ['X-TIMESTAMP' => date('c')]);
        }


        return null;
    }
    //endregion validator
}
