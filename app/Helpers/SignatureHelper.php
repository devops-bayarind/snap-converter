<?php

namespace App\Helpers;

class SignatureHelper
{
    public static  function signAsymmetricSignature($stringToSign, $privateKey){
        $binarySignature = "";
        openssl_sign($stringToSign, $binarySignature, $privateKey, "SHA256");
        return base64_encode($binarySignature);
    }

    public static function verifyAsymmetricSignature($signature, $stringToSign, $publicKey){
        $isValid = openssl_verify($stringToSign, base64_decode($signature), $publicKey, "sha256WithRSAEncryption");
        return ($isValid == 1);
    }



    public static function createStringToSign($httpMethod, $relativePath, $requestBody, $timeStamp)
    {
        //minify request body
        $minifyRequest = "";
        if (is_array($requestBody) || is_object($requestBody)){
            $minifyRequest = json_encode($requestBody, JSON_UNESCAPED_SLASHES);
        }else if (is_string($requestBody)){
            $minifyRequest = json_encode(json_decode($requestBody),JSON_UNESCAPED_SLASHES);
        }


        //hash sha 256
        $hashRequestBody = hash('sha256', $minifyRequest);
        $lowercaseHashBody = strtolower($hashRequestBody);

        //generate string to sign
        $stringToSign = $httpMethod.":". $relativePath . ":" . $lowercaseHashBody . ":" . $timeStamp;
        return $stringToSign;
    }


}
