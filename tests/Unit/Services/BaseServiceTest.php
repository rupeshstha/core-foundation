<?php

namespace CoreFoundation\Tests\Unit\Services;

use Closure;
use CoreFoundation\Services\BaseService;
use CoreFoundation\Tests\PackageTestCase;

class TestService extends BaseService
{
    public function doSomething($data)
    {
        return $this->throughPipes('doSomething', $data, function ($data) {
            return $data.' worked';
        });
    }
}

class TestPipe
{
    public function handle($data, Closure $next)
    {
        $data = 'Pipe '.$data;

        return $next($data);
    }
}

class BaseServiceTest extends PackageTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestService::clearAllPipes();
    }

    public function test_it_can_run_through_pipes(): void
    {
        TestService::addPipe('doSomething', TestPipe::class);

        $service = new TestService;
        $result = $service->doSomething('it');

        $this->assertEquals('Pipe it worked', $result);
    }

    public function test_it_runs_directly_without_pipes(): void
    {
        $service = new TestService;
        $result = $service->doSomething('it');

        $this->assertEquals('it worked', $result);
    }
}
