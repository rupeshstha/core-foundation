<?php

namespace CoreFoundation\Licensing;

use Illuminate\Support\Facades\Http;

/**
 * TODO make a different package and manage license from there.
 *
 * @see License
 */
class LicenseManager
{
    protected static $host = 'https://rupeshstha.com.np';

    public static function fetch($package)
    {
        Http::fake([
            '*' => Http::response([
                'id' => '123456',
                'licensed' => (bool) ($package['license'] ?? false),
                'verified' => true,
                'url' => 'https://rupeshstha.com.np',
                'seller' => 'Rupesh',
                'latestVersion' => '1.2.0',
                'domain' => 'http://myaddon.com',
            ], 200),
        ]);

        $response = Http::get(self::$host.'/licenses', [
            'package' => $package,
        ]);

        return (new License)->fill($response->json());
    }

    /**
     * TODO: This is for testing only and will never go to production.
     */
    public static function fetchFail($package)
    {
        Http::fake([
            '*' => Http::response([
                'id' => 7891011,
                'licensed' => false,
                'verified' => true,
                'slug' => 'foo-bar',
                'seller' => 'Rupesh',
                'current_version' => '1.0.0',
                'latest_version' => '1.2.0',
                'domain' => 'http://myaddon.com',
            ], 200),
        ]);

        $response = Http::get(self::$host.'/licenses/fail', [
            'package' => $package,
        ]);

        return (new License)->fill($response->json());
    }
}
