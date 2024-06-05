<?php

namespace App\Http\Middleware;

use App\Helpers\CommonHelper;
use App\Helpers\SignatureHelper;
use Closure;
use Illuminate\Http\Request;

class SnapAuthentication
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {

        $signature = $request->header("X-SIGNATURE") ?? "";
        $timestamp = $request->header("X-TIMESTAMP") ?? "";
        $partnerId = $request->header("X-PARTNER-ID") ?? "";
        $externalId = $request->header("X-EXTERNAL-ID") ?? "";
        $channelId = $request->header("CHANNEL-ID") ?? "";
        $apiServiceCode = $this->getApiServiceCode();
        $validateHeader = $this->validateHeader($request, $apiServiceCode);
        if (!is_null($validateHeader)){
            return $validateHeader;
        }


        //region verify signature
        //generate string to sign

        $slash = (substr(env('APP_URL'),-1) != "/") ? "/" : "";
        $url = parse_url(env('APP_URL').$slash.$request->path());
        $relativePath = "";
        if (isset($url["path"])){
            $relativePath = $url["path"];
        }
        $stringToSign = SignatureHelper::createStringToSign($request->method(), $relativePath,$request->getContent(), $timestamp);
        CommonHelper::Log("Inbound [".$apiServiceCode."] StringToSign: ".$stringToSign);
//        Load public key

        $publicKey = openssl_pkey_get_public(env('BAYARIND_PUBLIC_KEY_PATH'));
        if (!$publicKey){
            CommonHelper::Log("Invalid public Key: ".openssl_error_string());
            if (!file_exists(env('BAYARIND_PUBLIC_KEY_PATH'))){
                CommonHelper::Log("Not valid public key path: ". env('BAYARIND_PUBLIC_KEY_PATH'));
            }
            return response()->json([
                "responseCode" => "401" . $apiServiceCode . "00",
                "responseMessage" => "Unauthorized Signature",
            ]);
        }


        if (!SignatureHelper::verifyAsymmetricSignature($signature,$stringToSign,$publicKey)){
            return response()->json([
                "responseCode" => "401" . $apiServiceCode . "00",
                "responseMessage" => "Unauthorized Signature",
            ]);
        }
        //endregion verify signature

        return $next($request);
    }


    public function validateHeader(Request $request, $apiServiceCode)
    {
        if (empty(($request->header("X-SIGNATURE") ?? ""))) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "02",
                "responseMessage" => "Missing mandatory field x-signature",
            ]);
        }
        if (empty(($request->header("X-TIMESTAMP") ?? ""))) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "02",
                "responseMessage" => "Missing mandatory field x-timestamp",
            ]);
        }
        if (empty(($request->header("X-PARTNER-ID") ?? ""))) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "02",
                "responseMessage" => "Missing mandatory field x-partner-id",
            ]);
        }
        if (empty(($request->header("X-EXTERNAL-ID") ?? ""))) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "02",
                "responseMessage" => "Missing mandatory field x-external-id",
            ]);
        }
        if (empty(($request->header("CHANNEL-ID") ?? ""))) {
            return response()->json([
                "responseCode" => "400" . $apiServiceCode . "02",
                "responseMessage" => "Missing mandatory field channel-id",
            ]);
        }

        return null;
    }

    private function getApiServiceCode(): string
    {
        $segment = request()->segment(count(request()->segments()));

        switch ($segment) {
            case 'inquiry':
                return "24";

            case 'payment':
                return "25";
            default:
                return "00";

        }
    }
}
