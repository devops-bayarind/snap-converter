<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            $this->renderable(function (Throwable $e) {
                //
                Log::info("Internal Error",[
                    "error" => $e->getMessage(),
                    "code" => $e->getCode(),
                    "trace" => $e->getTrace(),
                ]);
                return response()->json([
                    "responseCode"=>  "5000001",
                    "responseMessage" => "Internal Server Error"
                ], 200, ['X-TIMESTAMP' => date('c')]);
            });
        });
    }
}
