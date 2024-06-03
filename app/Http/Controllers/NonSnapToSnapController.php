<?php

namespace App\Http\Controllers;

use App\Helpers\CommonHelper;
use App\Helpers\SignatureHelper;
use App\Helpers\SnapConverter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class NonSnapToSnapController extends Controller
{
    public function createVa(Request $request)
    {

        $validateCreateVa = $this->validateCreateVa($request);
        if (!is_null($validateCreateVa)) {
            return $validateCreateVa;
        }

        $authCode = hash('SHA256',
            ($request->input('transactionNo') ?? "")
            . ($request->input('transactionAmount') ?? "")
            . ($request->input('channelId') ?? "")
            . (env('BAYARIND_SECRET_KEY'))
        );
        if (empty(($request->input('authCode') ?? "")) || ($request->input('authCode') ?? "") != $authCode) {
            CommonHelper::Log("Insert Va ['Invalid auth code']");
            return response()->json([
                "channelId" => ($request->input('channelId') ?? ""),
                "currency" => ($request->input('currency') ?? ""),
                "insertStatus" => "01",
                "insertMessage" => "Invalid auth code",
                "insertId" => "",
                "additionalData" => ""
            ]);
        }

        //convert request body from non snap to snap
        $snapRequestCreateVaBody = SnapConverter::convertRequestBodyCreateVaNonSnapToSnap($request);

        //region generate header
        $timeStamp = date('c', strtotime(($request->input('transactionDate') ?? "")));
        $httpMethod = "POST";
        $relativePath = "/api/v1.0/transfer-va/create-va";

        //region prepare signature

        //create string to sign
        $stringToSign = SignatureHelper::createStringToSign($httpMethod, $relativePath, $snapRequestCreateVaBody, $timeStamp);
        CommonHelper::Log("Create VA StringToSign: ".$stringToSign);

        //load private key
        $privateKey = openssl_pkey_get_private(env('PRIVATE_KEY_PATH'));
        if (!$privateKey) {
            CommonHelper::Log("Invalid Private Key: ".openssl_error_string());
            if (!file_exists(env('PRIVATE_KEY_PATH'))){
                CommonHelper::Log("Not valid private key path: ". env('PRIVATE_KEY_PATH'));
            }
            return response()->json([
                "channelId" => ($request->input('channelId') ?? ""),
                "currency" => ($request->input('currency') ?? ""),
                "insertStatus" => "01",
                "insertMessage" => "Your transaction cannot be processed",
                "insertId" => "",
                "additionalData" => ""
            ]);
        }

        //generate signature
        $signature = SignatureHelper::signAsymmetricSignature($stringToSign, $privateKey);

        //endregion prepare signature

        $header = [
            "Content-Type" => "application/json",
            "X-PARTNER-ID" => ($request->input("channelId") ?? ""),
            "CHANNEL-ID" => ($request->input("serviceCode") ?? ""),
            "X-TIMESTAMP" => $timeStamp,
            "X-EXTERNAL-ID" => uniqid(time()),
            "X-SIGNATURE" => $signature
        ];
        //endregion generate header

        //region send create va with snap format
        $snapCreateVaUrl = "https://snaptest.bayarind.id$relativePath";
        CommonHelper::Log("Snap CreateVa Request URL: " . $snapCreateVaUrl);
        if (env('APP_ENV') != "production") {
            CommonHelper::Log("Snap CreateVa Request Header: " . json_encode($header, JSON_UNESCAPED_SLASHES));
        } else {
            CommonHelper::Log("Snap CreateVa Request Header: " . json_encode(array_merge($header, ["X-SIGNATURE" => "***********"]), JSON_UNESCAPED_SLASHES));
        }

        CommonHelper::Log("Snap CreateVa Request Body: " . json_encode($snapRequestCreateVaBody, JSON_UNESCAPED_SLASHES));
        try {
            $response = Http::withHeaders(
                $header
            )->post($snapCreateVaUrl, $snapRequestCreateVaBody);
            CommonHelper::Log("Snap CreateVa Response Body: " . $response->body());
            if ($response->successful()) {
                $snapResponse = $response->json();

                //convert response body from snap to non snap
                $nonSnapResponse = SnapConverter::convertResponseBodyCreateVaSnapToNonSnap($request, $snapResponse);
                return response()->json($nonSnapResponse);
            }
        }catch (\Exception $exception) {
            CommonHelper::Log("Snap CreateVa Response Exception: " . $exception->getMessage());
        }
        //endregion send create va with snap format

        return response()->json([
            "channelId" => ($request->input("channelId") ?? ""),
            "currency" => "",
            "insertStatus" => "01",
            "insertMessage" => "Failed",
            "additionalData" => ""
        ]);
    }

    public function queryStatus(Request $request)
    {

        //query status validation
        $validateQueryStatus = $this->validateQueryVa($request);
        if (!is_null($validateQueryStatus)) {
            return $validateQueryStatus;
        }

        //convert request body from non snap to snap
        $snapRequestInquiryStatusBody = SnapConverter::convertRequestBodyInquiryStatusVaNonSnapToSnap($request);

        //region generate header
        $timeStamp = date('c');
        $httpMethod = "POST";
        $relativePath = "/api/v1.0/transfer-va/status";

        //region prepare signature

        //create string to sign
        $stringToSign = SignatureHelper::createStringToSign($httpMethod, $relativePath, $snapRequestInquiryStatusBody, $timeStamp);
        CommonHelper::Log("Query Status StringToSign: ".$stringToSign);
        //load private key
        $privateKey = openssl_pkey_get_private(env('PRIVATE_KEY_PATH'));
        if (!$privateKey) {
            CommonHelper::Log("Invalid Private Key");
            return response()->json(
                [
                    "channelId" => ($request->input("channelId") ?? ""),
                    "queryResponse" => "Invalid configurator"
                ]
            );
        }

        //generate signature
        $signature = SignatureHelper::signAsymmetricSignature($stringToSign, $privateKey);

        //endregion prepare signature


        $header = [
            "Content-Type" => "application/json",
            "X-PARTNER-ID" => ($request->input("channelId") ?? ""),
            "CHANNEL-ID" => ($request->input("serviceCode") ?? ""),
            "X-TIMESTAMP" => $timeStamp,
            "X-EXTERNAL-ID" => uniqid(time()),
            "X-SIGNATURE" => $signature
        ];
        //endregion generate header

        //region send inquiry status va with snap format
        $snapInquiryStatusUrl = "https://snaptest.bayarind.id$relativePath";
        CommonHelper::Log("Snap InquiryStatus Request URL: " . $snapInquiryStatusUrl);
        if (env('APP_ENV') != "production") {
            CommonHelper::Log("Snap InquiryStatus Request Header: " . json_encode($header, JSON_UNESCAPED_SLASHES));
        } else {
            CommonHelper::Log("Snap InquiryStatus Request Header: " . json_encode(array_merge($header, ["X-SIGNATURE" => "***********"]), JSON_UNESCAPED_SLASHES));
        }
        CommonHelper::Log("Snap InquiryStatus Request Body: " . json_encode($snapRequestInquiryStatusBody, JSON_UNESCAPED_SLASHES));

        try {
            $response = Http::withHeaders(
                $header
            )->post($snapInquiryStatusUrl, $snapRequestInquiryStatusBody);
            CommonHelper::Log("Snap InquiryStatus Response Body: " . $response->body());
            if ($response->successful()) {
                $snapResponse = $response->json();

                //convert response body from snap to non snap
                $nonSnapResponse = SnapConverter::convertResponseBodyInquiryStatusVaSnapToNonSnap($request, $snapResponse);
                return response()->json($nonSnapResponse);
            }
        }catch (\Exception $exception) {
            CommonHelper::Log("Snap InquiryStatus Response Exception: " . $exception->getMessage());
        }

        //endregion send inquiry status va with snap format
        return response()->json(
            [
                "channelId" => ($request->input("channelId") ?? ""),
                "queryResponse" => "External Server Error"
            ]
        );

    }

    public function voidTransaction(Request $request)
    {

        //query status validation
        $validateVoid = $this->validateVoidVa($request);
        if (!is_null($validateVoid)) {
            return $validateVoid;
        }

        //convert request body from non snap to snap
        $snapDeleteVaRequestBody = SnapConverter::convertRequestBodyVoidVaNonSnapToSnap($request);

        //region generate header
        $timeStamp = date('c');
        $httpMethod = "DELETE";
        $relativePath = "/api/v1.0/transfer-va/delete-va";

        //region prepare signature

        //create string to sign
        $stringToSign = SignatureHelper::createStringToSign($httpMethod, $relativePath, $snapDeleteVaRequestBody, $timeStamp);
        CommonHelper::Log("Void VA StringToSign: ".$stringToSign);
        //load private key
        $privateKey = openssl_pkey_get_private(env('PRIVATE_KEY_PATH'));
        if (!$privateKey) {
            CommonHelper::Log("Invalid Private Key");
            return response()->json(
                [
                    "channelId" => ($request->input("channelId") ?? ""),
                    "transactionNo" => "",
                    "transactionAmount" => "0",
                    "transactionStatus" => "01",
                    "transactionMessage" => "General error",
                    "transactionType" => "VOID INSERT",
                ]
            );
        }

        //generate signature
        $signature = SignatureHelper::signAsymmetricSignature($stringToSign, $privateKey);

        //endregion prepare signature


        $header = [
            "Content-Type" => "application/json",
            "X-PARTNER-ID" => ($request->input("channelId") ?? ""),
            "CHANNEL-ID" => ($request->input("serviceCode") ?? ""),
            "X-TIMESTAMP" => $timeStamp,
            "X-EXTERNAL-ID" => uniqid(time()),
            "X-SIGNATURE" => $signature
        ];
        //endregion generate header

        //region send inquiry status va with snap format
        $snaDeleteVaUrl = "https://snaptest.bayarind.id$relativePath";
        CommonHelper::Log("Snap DeleteVA Request URL: " . $snaDeleteVaUrl);
        if (env('APP_ENV') != "production") {
            CommonHelper::Log("Snap DeleteVA Request Header: " . json_encode($header, JSON_UNESCAPED_SLASHES));
        } else {
            CommonHelper::Log("Snap DeleteVA Request Header: " . json_encode(array_merge($header, ["X-SIGNATURE" => "***********"]), JSON_UNESCAPED_SLASHES));
        }
        CommonHelper::Log("Snap DeleteVA Request Body: " . json_encode($snapDeleteVaRequestBody, JSON_UNESCAPED_SLASHES));

        try {
            $response = Http::withHeaders(
                $header
            )->delete($snaDeleteVaUrl, $snapDeleteVaRequestBody);
            CommonHelper::Log("Snap DeleteVA Response Body: " . $response->body());
            if ($response->successful()) {
                $snapResponse = $response->json();
                //convert response body from snap to non snap
                $nonSnapResponse = SnapConverter::convertResponseBodyVoidVaSnapToNonSnap($request, $snapResponse);
                return response()->json($nonSnapResponse);
            }
        }catch (\Exception $exception) {
            CommonHelper::Log("Snap DeleteVA Response Exception: " . $exception->getMessage());
        }

        //endregion send inquiry status va with snap format
        return response()->json(
            [
                "channelId" => ($request->input("channelId") ?? ""),
                "transactionNo" => "",
                "transactionAmount" => "0",
                "transactionStatus" => "01",
                "transactionMessage" => "General error",
                "transactionType" => "VOID INSERT",
            ]
        );
    }

    public function validateCreateVa(Request $request): ?\Illuminate\Http\JsonResponse
    {
        if (empty($request->input('channelId') ?? "")) {
            return response()->json([
                "channelId" => ($request->input('channelId') ?? ""),
                "currency" => ($request->input('currency') ?? ""),
                "insertStatus" => "01",
                "insertMessage" => "channelId cant be empty",
                "insertId" => "",
                "additionalData" => ""
            ]);
        }

        if (empty($request->input('serviceCode') ?? "")) {
            return response()->json([
                "channelId" => ($request->input('channelId') ?? ""),
                "currency" => ($request->input('currency') ?? ""),
                "insertStatus" => "01",
                "insertMessage" => "serviceCode cant be empty",
                "insertId" => "",
                "additionalData" => ""
            ]);
        }

        if (empty($request->input('transactionNo') ?? "")) {
            return response()->json([
                "channelId" => ($request->input('channelId') ?? ""),
                "currency" => ($request->input('currency') ?? ""),
                "insertStatus" => "01",
                "insertMessage" => "transactionNo cant be empty",
                "insertId" => "",
                "additionalData" => ""
            ]);
        }

        if (empty($request->input('customerAccount') ?? "")) {
            return response()->json([
                "channelId" => ($request->input('channelId') ?? ""),
                "currency" => ($request->input('currency') ?? ""),
                "insertStatus" => "01",
                "insertMessage" => "customerAccount cant be empty",
                "insertId" => "",
                "additionalData" => ""
            ]);
        }

        if (trim(($request->input('transactionAmount') ?? "")) == "") {
            return response()->json([
                "channelId" => ($request->input('channelId') ?? ""),
                "currency" => ($request->input('currency') ?? ""),
                "insertStatus" => "01",
                "insertMessage" => "transactionAmount cant be empty",
                "insertId" => "",
                "additionalData" => ""
            ]);
        }


        if (empty($request->input('customerName') ?? "")) {
            return response()->json([
                "channelId" => ($request->input('channelId') ?? ""),
                "currency" => ($request->input('currency') ?? ""),
                "insertStatus" => "01",
                "insertMessage" => "customerName cant be empty",
                "insertId" => "",
                "additionalData" => ""
            ]);
        }

        if (empty($request->input('transactionDate') ?? "")) {
            return response()->json([
                "channelId" => ($request->input('channelId') ?? ""),
                "currency" => ($request->input('currency') ?? ""),
                "insertStatus" => "01",
                "insertMessage" => "Invalid transactionDate",
                "insertId" => "",
                "additionalData" => ""
            ]);
        }

        if (!CommonHelper::checkDateFormat($request->input('transactionDate'))){
            return response()->json([
                "channelId" => ($request->input('channelId') ?? ""),
                "currency" => ($request->input('currency') ?? ""),
                "insertStatus" => "01",
                "insertMessage" => "Invalid transaction date format",
                "insertId" => "",
                "additionalData" => ""
            ]);
        }

        if (empty($request->input('transactionExpire') ?? "")) {
            return response()->json([
                "channelId" => ($request->input('channelId') ?? ""),
                "currency" => ($request->input('currency') ?? ""),
                "insertStatus" => "01",
                "insertMessage" => "Invalid transactionExpire",
                "insertId" => "",
                "additionalData" => ""
            ]);
        }

        if (!CommonHelper::checkDateFormat($request->input('transactionExpire'))){
            return response()->json([
                "channelId" => ($request->input('channelId') ?? ""),
                "currency" => ($request->input('currency') ?? ""),
                "insertStatus" => "01",
                "insertMessage" => "Invalid transaction expired Date format",
                "insertId" => "",
                "additionalData" => ""
            ]);
        }


        return null;
    }

    public function validateQueryVa(Request $request): ?\Illuminate\Http\JsonResponse
    {
        if (empty($request->input('channelId') ?? "")) {
            return response()->json(
                [
                    "channelId" => ($request->input("channelId") ?? ""),
                    "queryResponse" => "channelId cant be empty"
                ]
            );
        }

        if (empty($request->input('serviceCode') ?? "")) {
            return response()->json(
                [
                    "channelId" => ($request->input("channelId") ?? ""),
                    "queryResponse" => "serviceCode cant be empty"
                ]
            );
        }

        //validate query request
        if (!$request->has("queryRequest")) {
            return response()->json(
                [
                    "channelId" => ($request->input("channelId") ?? ""),
                    "queryResponse" => "Invalid queryRequest Format"
                ]
            );
        }
        $jsonQueryRequest = $request->input("queryRequest");
        if (!is_array($jsonQueryRequest)) {
            if (!!json_encode($jsonQueryRequest, true)){
                $jsonQueryRequest = json_decode($jsonQueryRequest, true);
            }
        }

        if (!is_array($jsonQueryRequest)) {
            return response()->json(
                [
                    "channelId" => ($request->input("channelId") ?? ""),
                    "queryResponse" => "Invalid queryRequest Format"
                ]
            );
        }

        if (empty($jsonQueryRequest)) {
            return response()->json(
                [
                    "channelId" => ($request->input("channelId") ?? ""),
                    "queryResponse" => "Invalid queryRequest Format"
                ]
            );
        }

        if (empty($jsonQueryRequest[0])) {
            return response()->json(
                [
                    "channelId" => ($request->input("channelId") ?? ""),
                    "queryResponse" => "Invalid queryRequest Format"
                ]
            );
        }
        if (empty(($jsonQueryRequest[0]["transactionNo"] ?? "")) || empty(($jsonQueryRequest[0]["transactionDate"] ?? ""))) {
            return response()->json(
                [
                    "channelId" => ($request->input("channelId") ?? ""),
                    "queryResponse" => "Invalid queryRequest Format"
                ]
            );
        }


        return null;
    }

    public function validateVoidVa(Request $request): ?\Illuminate\Http\JsonResponse
    {

        if (empty($request->input('serviceCode') ?? "")) {
            return response()->json(
                [
                    "channelId" => ($request->input("channelId") ?? ""),
                    "transactionNo" => "",
                    "transactionAmount" => "0",
                    "transactionStatus" => "01",
                    "transactionMessage" => "serviceCode cant be empty",
                    "transactionType" => "VOID INSERT",
                ]
            );
        }

        if (empty($request->input('channelId') ?? "")) {
            return response()->json(
                [
                    "channelId" => ($request->input("channelId") ?? ""),
                    "transactionNo" => "",
                    "transactionAmount" => "0",
                    "transactionStatus" => "01",
                    "transactionMessage" => "channelId cant be empty",
                    "transactionType" => "VOID INSERT",
                ]
            );
        }

        if (empty($request->input('transactionNo') ?? "")) {
            return response()->json(
                [
                    "channelId" => ($request->input("channelId") ?? ""),
                    "transactionNo" => "",
                    "transactionAmount" => "0",
                    "transactionStatus" => "01",
                    "transactionMessage" => "transactionNo cant be empty",
                    "transactionType" => "VOID INSERT",
                ]
            );
        }


        return null;
    }


}
