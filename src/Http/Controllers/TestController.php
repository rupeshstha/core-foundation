<?php

namespace CoreFoundation\Http\Controllers;

use CoreFoundation\Facades\ServerTiming;
use CoreFoundation\Services\BaseManifest;
use CoreFoundation\Services\TestService;
use Exception;
use Illuminate\Http\Request;

/**
 * @property TestService $baseManifest
 */
class TestController extends BaseController
{
    public function __construct(
        protected TestService $testService,
        protected BaseManifest $baseManifest
    ) {}

    public function resolve()
    {
        /**
         * @property TestService $baseManifest
         */
        $this->baseManifest->resolveTo();
    }

    public function test(Request $request)
    {
        try {
            $data = $request->all();
            // $this->baseManifest->index($data, ["user"]);

            $this->testService->factory()->index($data, ['user']);
        } catch (Exception $exception) {
            dd($exception);

            return $this->handleException($exception);
        }

        dd('asdad');
    }

    public function testTiming()
    {
        ServerTiming::start('Running expensive task');
        sleep(10);
        ServerTiming::stop('Running expensive task');
        ServerTiming::addMetric('User: '.'adsasd');

        throw new Exception('not found');
    }
}
