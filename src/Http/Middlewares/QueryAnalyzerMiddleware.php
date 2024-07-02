<?php

namespace CoreFoundation\Http\Middlewares;

use Closure;
use Illuminate\Http\Request;

class QueryAnalyzerMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $requestHeader = $request->header("x-query-analyze", false);
        $analyzeQuery = config("core_foundation.query.analyze_query", true);
        if ($requestHeader || $analyzeQuery) {
            //
        }
        return $next();
    }
}
