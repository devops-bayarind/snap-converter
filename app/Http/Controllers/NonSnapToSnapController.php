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
        //convert request body from non snap to snap
        $snapRequestCreateVaBody = SnapConverter::convertRequestBodyCreateVaNonSnapToSnap($request);

        //region generate header
        $timeStamp = date('c', strtotime(($request->input('transactionDate') ?? "")));
        $httpMethod = "POST";
        $relativePath = "/api/v1.0/transfer-va/create-va";

        //region prepare signature

        //create string to sign
        $stringToSign = SignatureHelper::createStringToSign($httpMethod, $relativePath, $snapRequestCreateVaBody, $timeStamp);

        //load private key
        $privateKey = file_get_contents(env('PRIVATE_KEY_PATH'));

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
        CommonHelper::Log("Snap CreateVa Request URL: ".$snapCreateVaUrl);
        CommonHelper::Log("Snap CreateVa Request Header: ".json_encode(array_merge($header ,["X-SIGNATURE" => "***********"]), JSON_UNESCAPED_SLASHES));
        CommonHelper::Log("Snap CreateVa Request Body: ".json_encode($snapRequestCreateVaBody, JSON_UNESCAPED_SLASHES));
        $response = Http::withHeaders(
            $header
        )->post($snapCreateVaUrl, $snapRequestCreateVaBody);
        CommonHelper::Log("Snap CreateVa Response Body: ".$response->body());
        if ($response->successful()) {
            $snapResponse = $response->json();

            //convert response body from snap to non snap
            $nonSnapResponse = SnapConverter::convertResponseBodyCreateVaSnapToNonSnap($request, $snapResponse);
            return response()->json($nonSnapResponse);
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
}
