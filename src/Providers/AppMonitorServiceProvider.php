<?php

namespace CoreFoundation\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class AppMonitorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
    }

    public function register(): void
    {
        // Log every single query on query.log file.
        DB::listen(function ($query) {
            //Write requested query on file
            File::append(
                path: storage_path('/logs/query.log'),
                data: $query->sql . ' [' . implode(', ', $query->bindings) . ']' . PHP_EOL
            );
        });
    }
}
