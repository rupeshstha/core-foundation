<?php

namespace CoreFoundation\Tests\Unit\Traits;

use Closure;
use CoreFoundation\Services\BaseService;
use CoreFoundation\Tests\PackageTestCase;

class PipelineService extends BaseService
{
    public function run(string $payload): string
    {
        return $this->throughPipes('run', $payload, fn ($p) => $p.' [core]');
    }
}

class PrependPipe
{
    public function handle(string $payload, Closure $next): string
    {
        return $next('[A] '.$payload);
    }
}

class AppendPipe
{
    public function handle(string $payload, Closure $next): string
    {
        $result = $next($payload);

        return $result.' [B]';
    }
}

class SecondPipe
{
    public function handle(string $payload, Closure $next): string
    {
        return $next('[C] '.$payload);
    }
}

class HasPipelineTest extends PackageTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PipelineService::clearAllPipes();
    }

    public function test_runs_core_directly_when_no_pipes(): void
    {
        $service = new PipelineService;
        $result = $service->run('input');

        $this->assertEquals('input [core]', $result);
    }

    public function test_pipe_wraps_core_execution(): void
    {
        PipelineService::addPipe('run', PrependPipe::class);

        $service = new PipelineService;
        $result = $service->run('input');

        $this->assertEquals('[A] input [core]', $result);
    }

    public function test_multiple_pipes_run_in_registration_order(): void
    {
        PipelineService::addPipe('run', PrependPipe::class);
        PipelineService::addPipe('run', SecondPipe::class);

        $service = new PipelineService;
        $result = $service->run('x');

        // PrependPipe runs first: '[A] x', then SecondPipe: '[C] [A] x', then core: '[C] [A] x [core]'
        $this->assertEquals('[C] [A] x [core]', $result);
    }

    public function test_pipe_can_modify_result_after_core(): void
    {
        PipelineService::addPipe('run', AppendPipe::class);

        $service = new PipelineService;
        $result = $service->run('x');

        $this->assertEquals('x [core] [B]', $result);
    }

    public function test_get_pipes_returns_registered_class_names(): void
    {
        PipelineService::addPipe('run', PrependPipe::class);
        PipelineService::addPipe('run', SecondPipe::class);

        $service = new PipelineService;
        $pipes = $service->getPipes('run');

        $this->assertEquals([PrependPipe::class, SecondPipe::class], $pipes);
    }

    public function test_get_pipes_returns_empty_for_unregistered_hook(): void
    {
        $service = new PipelineService;
        $this->assertEquals([], $service->getPipes('nonexistent'));
    }

    public function test_clear_pipes_removes_hook_pipes(): void
    {
        PipelineService::addPipe('run', PrependPipe::class);
        PipelineService::clearPipes('run');

        $service = new PipelineService;
        $this->assertEquals([], $service->getPipes('run'));
    }

    public function test_clear_all_pipes_removes_all_hooks(): void
    {
        PipelineService::addPipe('run', PrependPipe::class);
        PipelineService::addPipe('other', SecondPipe::class);
        PipelineService::clearAllPipes();

        $service = new PipelineService;
        $this->assertEquals([], $service->getPipes('run'));
        $this->assertEquals([], $service->getPipes('other'));
    }

    public function test_pipes_isolated_between_service_classes(): void
    {
        $otherService = new class extends BaseService {
            public function doWork(string $p): string
            {
                return $this->throughPipes('work', $p, fn ($p) => $p);
            }
        };

        PipelineService::addPipe('run', PrependPipe::class);

        // The anonymous service class has no pipes
        $result = $otherService->doWork('clean');
        $this->assertEquals('clean', $result);
    }
}
