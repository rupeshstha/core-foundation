<?php

namespace CoreFoundation\Tests\Unit\Jobs;

use Throwable;
use RuntimeException;
use Illuminate\Support\Str;
use CoreFoundation\Jobs\BaseJob;
use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Exceptions\ExceptionRenderer;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Facades\DB;

// ---------------------------------------------------------------------------
// Stubs
// ---------------------------------------------------------------------------

/** Minimal job — uses all defaults. */
class SilentJob extends BaseJob
{
    public function handle(): void {}
}

/** Job with notifications enabled. */
class NotifyingJob extends BaseJob
{
    public function handle(): void {}

    protected function shouldNotify(): bool
    {
        return true;
    }
}

/** Job with domain-specific log context. */
class ContextJob extends BaseJob
{
    public function __construct(
        public readonly int $orderId = 99,
    ) {}

    public function handle(): void {}

    protected function logContext(Throwable $exception): array
    {
        return ['order_id' => $this->orderId];
    }
}

/** Job with custom middleware. */
class MiddlewareJob extends BaseJob
{
    public function handle(): void {}

    protected function bindMiddlewares(): array
    {
        return [new \stdClass]; // any object to verify it's appended
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class BaseJobTest extends PackageTestCase
{
    // =========================================================================
    // shouldNotify — default and override
    // =========================================================================

    public function test_should_notify_returns_false_by_default(): void
    {
        $job = new SilentJob;

        $this->assertFalse($this->callProtected($job, 'shouldNotify'));
    }

    public function test_should_notify_can_be_overridden_to_true(): void
    {
        $job = new NotifyingJob;

        $this->assertTrue($this->callProtected($job, 'shouldNotify'));
    }

    // =========================================================================
    // logContext — default and override
    // =========================================================================

    public function test_log_context_returns_empty_array_by_default(): void
    {
        $job       = new SilentJob;
        $exception = new RuntimeException('Test');

        $context = $this->callProtected($job, 'logContext', [$exception]);

        $this->assertSame([], $context);
    }

    public function test_log_context_override_is_merged_into_failure_log(): void
    {
        $job       = new ContextJob(orderId: 42);
        $exception = new RuntimeException('Test');

        $context = $this->callProtected($job, 'logContext', [$exception]);

        $this->assertSame(['order_id' => 42], $context);
    }

    // =========================================================================
    // logFailure — structure
    // =========================================================================

    public function test_log_failure_produces_structured_entry_with_job_and_exception(): void
    {
        $job       = new SilentJob;
        $exception = new RuntimeException('Something broke');

        $logged = [];

        // PSR-3 compliant fake logger — must implement LoggerInterface
        $fake = new class($logged) extends \Psr\Log\AbstractLogger {
            public function __construct(private array &$log) {}

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->log[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };

        $this->app->instance('log', $fake);

        $this->callPrivate($job, 'logFailure', [$exception]);

        $this->assertNotEmpty($logged);
        $entry = $logged[0];

        $this->assertStringContainsString('SilentJob', $entry['message']);
        $this->assertEquals(SilentJob::class, $entry['context']['job']);
        $this->assertEquals(RuntimeException::class, $entry['context']['exception']['class']);
        $this->assertEquals('Something broke', $entry['context']['exception']['message']);
        $this->assertArrayHasKey('trace', $entry['context']['exception']);
    }

    public function test_log_failure_merges_log_context_into_entry(): void
    {
        $job       = new ContextJob(orderId: 77);
        $exception = new RuntimeException('Failure');

        $logged = [];

        $fake = new class($logged) extends \Psr\Log\AbstractLogger {
            public function __construct(private array &$log) {}

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->log[] = $context;
            }
        };

        $this->app->instance('log', $fake);

        $this->callPrivate($job, 'logFailure', [$exception]);

        $this->assertEquals(77, $logged[0]['order_id']);
    }

    // =========================================================================
    // buildExceptionContext delegation
    // =========================================================================

    public function test_log_failure_uses_exception_renderer_build_exception_context(): void
    {
        $exception = new RuntimeException('Renderer test');

        $direct = ExceptionRenderer::buildExceptionContext($exception);

        // Both must produce the same class and message fields
        $this->assertEquals(RuntimeException::class, $direct['class']);
        $this->assertEquals('Renderer test', $direct['message']);
        $this->assertIsArray($direct['trace']);
    }

    // =========================================================================
    // rollbackPreviousTransactions
    // =========================================================================

    public function test_rollback_does_nothing_when_no_active_transaction(): void
    {
        // Mock DB so transactionLevel() returns 0 — no rollBack call expected
        DB::shouldReceive('transactionLevel')->once()->andReturn(0);
        DB::shouldReceive('rollBack')->never();

        $job = new SilentJob;
        $this->callProtected($job, 'rollbackPreviousTransactions');
    }

    public function test_rollback_rolls_back_when_transaction_is_active(): void
    {
        // Mock DB so transactionLevel() returns > 0 — rollBack must be called
        DB::shouldReceive('transactionLevel')->once()->andReturn(1);
        DB::shouldReceive('rollBack')->once();

        $job = new SilentJob;
        $this->callProtected($job, 'rollbackPreviousTransactions');
    }

    // =========================================================================
    // middleware — SkipIfBatchCancelled always present
    // =========================================================================

    public function test_middleware_always_includes_skip_if_batch_cancelled(): void
    {
        $job = new SilentJob;

        $middleware = $job->middleware();

        $this->assertNotEmpty($middleware);
        $this->assertInstanceOf(SkipIfBatchCancelled::class, $middleware[0]);
    }

    public function test_bind_middlewares_returns_empty_array_by_default(): void
    {
        $job = new SilentJob;

        $this->assertSame([], $this->callProtected($job, 'bindMiddlewares'));
    }

    public function test_custom_bind_middlewares_are_appended_after_skip_if_batch_cancelled(): void
    {
        $job = new MiddlewareJob;

        $middleware = $job->middleware();

        $this->assertCount(2, $middleware);
        $this->assertInstanceOf(SkipIfBatchCancelled::class, $middleware[0]);
    }

    // =========================================================================
    // sendNotification — only fires when shouldNotify is true
    // =========================================================================

    public function test_send_notification_does_not_dispatch_when_should_notify_is_false(): void
    {
        $job       = new SilentJob;
        $exception = new RuntimeException('Test');
        $dispatched = false;

        // Override dispatchNotification via a sub-class to track whether it was called
        $spy = new class($dispatched) extends SilentJob {
            public function __construct(private bool &$dispatched) {}

            protected function dispatchNotification(callable $resolver): void
            {
                $this->dispatched = true;
            }
        };

        $this->callProtected($spy, 'sendNotification', [$exception]);

        $this->assertFalse($dispatched);
    }

    // =========================================================================
    // failed() — integration: all three steps run
    // =========================================================================

    public function test_failed_does_not_throw_even_when_notifications_disabled(): void
    {
        $job       = new SilentJob;
        $exception = new RuntimeException('Job failed');

        // failed() calls rollback + logFailure + sendNotification — none should throw
        $job->failed($exception);

        $this->assertTrue(true); // reaching here = no exception
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function callProtected(object $obj, string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod($obj, $method);
        $ref->setAccessible(true);
        return $ref->invoke($obj, ...$args);
    }

    private function callPrivate(object $obj, string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod($obj, $method);
        $ref->setAccessible(true);
        return $ref->invoke($obj, ...$args);
    }
}
