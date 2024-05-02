<?php

namespace App\Http\Middleware;

use App\Helpers\CommonHelper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RequestLogger
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        CommonHelper::Log("REQUEST URL: ".$request->url());
        CommonHelper::Log("REQUEST HEADER: ".json_encode($request->header()));
        CommonHelper::Log("REQUEST BODY: ".$request->getContent());
        $response = $next($request);
        CommonHelper::Log("RESPONSE BODY: ".$response->getContent());
        return $response;
    }
}
