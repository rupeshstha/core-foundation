<?php

namespace CoreFoundation\Jobs;

use Throwable;
use Illuminate\Support\Str;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use CoreFoundation\Traits\HasNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Facades\Notification as NotificationFacade;

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
        // send notification
        $this->rollbackPreviousTransactions();
        $this->setLogs($exception);
        $this->sendNotification($exception);
    }

    protected function bindMiddlewares(): array
    {
        return [];
    }

    protected function rollbackPreviousTransactions(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }

    protected function errorMessageUniqueKey(): string
    {
        return Str::slug(class_basename($this));
    }

    protected function errorContext(Throwable $exception): array
    {
        return [
            'trace' => $exception->getTrace(),
        ];
    }

    protected function setLogs(Throwable $exception): void
    {
        Log::error(
            message: $this->errorMessageUniqueKey().'| '.$exception->getMessage(),
            context: [
                [
                    'trace_line' => $exception->getLine(),
                    'trace_file' => $exception->getFile(),
                ],
                ...$this->errorContext($exception),
            ]
        );
    }

    protected function shouldNotify(bool $notify = false): bool
    {
        return $notify;
    }

    protected function additionalNotifiables(): array
    {
        return [];
    }

    final protected function notifiables(): array|object|null
    {
        return [
            NotificationFacade::route(
                channel: config('core_foundation.notifications.jobs.notifiables.channel'),
                route: config('core_foundation.notifications.jobs.notifiables.route')
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
        $this->dispatchNotification(fn () => $this->startedNotification());
    }

    final protected function notifyCompleted(): void
    {
        $this->dispatchNotification(fn () => $this->completedNotification());
    }

    /**
     * Notification to send when the job fails.
     * Triggered automatically by Laravel via failed() — no call needed.
     */
    protected function failedNotification(Throwable $exception): ?Notification
    {
        /** @var Notification $failedNotificationClass */
        $failedNotificationClass = config('core_foundation.notifications.jobs.failed');
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
        $startedNotificationClass = config('core_foundation.notifications.jobs.started');
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
        $completedNotificationClass = config('core_foundation.notifications.jobs.completed');
        if (! $completedNotificationClass) {
            return null;
        }

        return new $completedNotificationClass($this);
    }
}
