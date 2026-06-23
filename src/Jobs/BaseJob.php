<?php

namespace CoreFoundation\Jobs;

use Throwable;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use CoreFoundation\Traits\HasNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use CoreFoundation\Exceptions\ExceptionRenderer;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * BaseJob
 *
 * Foundation class for all queued jobs. Provides lifecycle hooks, structured
 * failure logging, optional notifications, and batch support.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ USAGE                                                                       │
 * │                                                                             │
 * │   final class SyncProductToSearchJob extends BaseJob                        │
 * │   {                                                                         │
 * │       public function __construct(                                          │
 * │           private readonly int $productId,                                  │
 * │       ) {}                                                                  │
 * │                                                                             │
 * │       public function handle(ProductService $service): void                 │
 * │       {                                                                     │
 * │           $this->notifyStarted();                                           │
 * │           $service->syncToSearch($this->productId);                         │
 * │           $this->notifyCompleted();                                         │
 * │       }                                                                     │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ NOTIFICATIONS                                                               │
 * │                                                                             │
 * │ Override shouldNotify() to enable failure/start/complete notifications:     │
 * │                                                                             │
 * │   protected function shouldNotify(): bool { return true; }                 │
 * │                                                                             │
 * │ Override failedNotification(), startedNotification(), completedNotification()
 * │ to return a custom Notification class instead of the config defaults.       │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ FAILURE LOGGING                                                             │
 * │                                                                             │
 * │ On failure, the job logs a structured error entry using the same context    │
 * │ builders as ExceptionRenderer — class, message, relative file:line, and    │
 * │ trimmed trace. Override logContext() to add domain-specific fields.         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
abstract class BaseJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use HasNotification;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function middleware(): array
    {
        return [
            new SkipIfBatchCancelled,
            ...$this->bindMiddlewares(),
        ];
    }

    public function failed(Throwable $exception): void
    {
        $this->rollbackPreviousTransactions();
        $this->logFailure($exception);
        $this->sendNotification($exception);
    }

    protected function bindMiddlewares(): array
    {
        return [];
    }

    private function rollbackPreviousTransactions(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }

    /**
     * Whether to send notifications on failure/start/complete.
     * Return true in subclasses that need alerting.
     */
    protected function shouldNotify(): bool
    {
        return false;
    }

    /**
     * Extra context merged into the failure log entry.
     * Override to add domain-specific fields (order_id, tenant_id, etc.).
     */
    protected function logContext(Throwable $exception): array
    {
        return [];
    }

    private function logFailure(Throwable $exception): void
    {
        logger()->error('Job failed: '.class_basename($this).'.', [
            'job' => static::class,
            'exception' => ExceptionRenderer::buildExceptionContext($exception),
            ...$this->logContext($exception),
        ]);
    }

    protected function additionalNotifiables(): array
    {
        return [];
    }

    final protected function notifiables(): array|object|null
    {
        return [
            NotificationFacade::route(
                channel: config('core-foundation.notifications.jobs.notifiables.channel'),
                route: config('core-foundation.notifications.jobs.notifiables.route')
            ),
            ...$this->additionalNotifiables(),
        ];
    }

    final protected function sendNotification(Throwable $exception): void
    {
        if ($this->shouldNotify()) {
            // send notification
            $this->dispatchNotification(fn () => $this->failedNotification($exception));
        }
    }

    final protected function notifyStarted(): void
    {
        if (! $this->shouldNotify()) {
            return;
        }

        $this->dispatchNotification(fn () => $this->startedNotification());
    }

    final protected function notifyCompleted(): void
    {
        if (! $this->shouldNotify()) {
            return;
        }

        $this->dispatchNotification(fn () => $this->completedNotification());
    }

    /**
     * Notification to send when the job fails.
     * Triggered automatically by Laravel via failed() — no call needed.
     */
    protected function failedNotification(Throwable $exception): ?Notification
    {
        /** @var Notification $failedNotificationClass */
        $failedNotificationClass = config('core-foundation.notifications.jobs.failed');
        if (! $failedNotificationClass) {
            return null;
        }

        return new $failedNotificationClass($this, $exception);
    }

    /**
     * Notification to send when the job starts.
     * Trigger manually: call $this->notifyStarted() inside handle().
     */
    protected function startedNotification(): ?Notification
    {
        /** @var Notification $startedNotificationClass */
        $startedNotificationClass = config('core-foundation.notifications.jobs.started');
        if (! $startedNotificationClass) {
            return null;
        }

        return new $startedNotificationClass($this);
    }

    /**
     * Notification to send when the job completes successfully.
     * Trigger manually: call $this->notifyCompleted() inside handle().
     */
    protected function completedNotification(): ?Notification
    {
        /** @var Notification $completedNotificationClass */
        $completedNotificationClass = config('core-foundation.notifications.jobs.completed');
        if (! $completedNotificationClass) {
            return null;
        }

        return new $completedNotificationClass($this);
    }
}
