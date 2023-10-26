<?php

namespace CoreFoundation\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use CoreFoundation\Services\TestService;

class TestController extends BaseController
{
    public function __construct(
        protected TestService $testService
    ) {
    }

    public function test(Request $request)
    {
        try {
            $data = $request->all();
            $this->testService->index();
        } catch (Exception $exception) {
            dd($exception);
            return $this->handleException($exception);
        }


        dd("asdad");
    }
}
