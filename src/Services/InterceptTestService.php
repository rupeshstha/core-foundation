<?php

namespace CoreFoundation\Services;

class InterceptTestService
{
    public function __construct(
        protected TestService $testService
    ) {}

    public function index()
    {
        dd('adsasdadad');
    }
}
