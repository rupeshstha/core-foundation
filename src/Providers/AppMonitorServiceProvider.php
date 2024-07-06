<?php

namespace CoreFoundation\Providers;

use CoreFoundation\Listeners\QueryAnalyzerListener;
use CoreFoundation\Services\Utils\Handler;
use Event;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class AppMonitorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen("query-analyzer", function () {
            
        });
    }

    public function register(): void
    {
        // Log every single query on query.log file.
        DB::listen(function ($query) {
            //Write requested query on file
            Event::dispatch(new MessageLogged("info", $query->sql, $query->bindings));
            Event::dispatch("query-analyzer", [
                "query" => $query->sql,
                "bindings" => $query->bindings,
            ]);
            File::append(
                path: storage_path('/logs/query.log'),
                data: $query->sql . ' [' . implode(', ', $query->bindings) . ']' . PHP_EOL
            );
        });
    }
}
