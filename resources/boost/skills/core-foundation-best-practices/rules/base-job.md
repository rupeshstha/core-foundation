# BaseJob Rules

## Basic Structure

```php
use CoreFoundation\Jobs\BaseJob;

class ProcessPaymentJob extends BaseJob
{
    public function __construct(
        private readonly int $orderId,
    ) {}

    public function handle(): void
    {
        $this->notifyStarted(); // optional

        // ... job logic ...

        $this->notifyCompleted(); // optional
    }
}
```

## `failed()` Is Handled Automatically — Never Override It

`failed()` is the built-in handler. It:
1. Rolls back any open DB transaction
2. Logs the error with context
3. Sends a notification if `shouldNotify()` returns `true`

Override `errorContext()` to enrich the log, not `failed()`:

Incorrect:
```php
public function failed(Throwable $exception): void
{
    Log::error('Job failed: ' . $exception->getMessage()); // bypasses built-in handling
}
```

Correct:
```php
protected function errorContext(Throwable $exception): array
{
    return array_merge(parent::errorContext($exception), [
        'order_id' => $this->orderId,
    ]);
}
```

## Notifications — Three Lifecycle Hooks

Enable notifications by overriding `shouldNotify()`:

```php
protected function shouldNotify(bool $notify = false): bool
{
    return true;
}
```

Then override whichever notification factory methods you need:

```php
protected function failedNotification(Throwable $exception): ?Notification
{
    return new JobFailedNotification($this, $exception); // auto-triggered by failed()
}

protected function startedNotification(): ?Notification
{
    return new JobStartedNotification($this); // trigger with $this->notifyStarted()
}

protected function completedNotification(): ?Notification
{
    return new JobCompletedNotification($this); // trigger with $this->notifyCompleted()
}
```

Configure notifiable channel and route in `config/core_foundation.php`:

```php
'notifications' => [
    'jobs' => [
        'failed'    => JobFailedNotification::class,
        'started'   => null,
        'completed' => null,
        'notifiables' => [
            'channel' => 'slack',
            'route'   => env('SLACK_JOB_WEBHOOK'),
        ],
    ],
],
```

## Do Not Add `SkipIfBatchCancelled` to `bindMiddlewares()`

It is prepended automatically. Adding it manually creates a duplicate.

Incorrect:
```php
protected function bindMiddlewares(): array
{
    return [
        new SkipIfBatchCancelled, // already added
        new RateLimited('payment-gateway'),
    ];
}
```

Correct:
```php
protected function bindMiddlewares(): array
{
    return [
        new RateLimited('payment-gateway'),
    ];
}
```

## Batch Support

`Batchable` is included. Jobs in a cancelled batch are skipped automatically via `SkipIfBatchCancelled`:

```php
Bus::batch([
    new ProcessPaymentJob($order1->id),
    new ProcessPaymentJob($order2->id),
])->then(fn () => Log::info('All payments processed'))
  ->catch(fn (Batch $batch, Throwable $e) => Log::error('Batch failed'))
  ->dispatch();
```

## `defer()` vs `BaseJob::dispatch()`

Use `defer()` (on `BaseService`) for lightweight, non-critical, no-retry work. Use `BaseJob::dispatch()` for:
- Work that must survive process crashes
- Work that needs retry logic
- Work that needs batching or chaining
- Sending emails, webhooks, or third-party API calls

## Dispatching

```php
ProcessPaymentJob::dispatch($order->id);
ProcessPaymentJob::dispatch($order->id)->delay(now()->addMinutes(5));
ProcessPaymentJob::dispatch($order->id)->onQueue('payments');
```
