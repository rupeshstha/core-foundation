# BaseJob Best Practices

## Enable Notifications by Overriding `shouldNotify()`

Notifications are opt-in. The default is `false` — override to `true` in jobs that need failure notifications.

```php
class ProcessOrderJob extends BaseJob
{
    protected function shouldNotify(): bool
    {
        return true;
    }

    public function handle(): void
    {
        $this->notifyStarted();       // call manually — opt-in
        // ... process order ...
        $this->notifyCompleted();     // call manually — opt-in
    }
}
```

## Never Override `failed()` — Override `errorContext()` Instead

`failed()` is marked `final`. It handles transaction rollback, logging, and notification dispatch automatically. Add domain context by overriding `errorContext()`.

Incorrect:
```php
public function failed(?Throwable $exception): void
{
    Log::error('Job failed', ['order' => $this->orderId]);
    // base class never runs — rollback and notification skipped
}
```

Correct:
```php
protected function errorContext(): array
{
    return ['order_id' => $this->orderId, 'merchant_id' => $this->merchantId];
}
```

## `SkipIfBatchCancelled` Is Prepended Automatically

Do not add it to `bindMiddlewares()` — it will run twice.

Incorrect:
```php
protected function bindMiddlewares(): array
{
    return [new SkipIfBatchCancelled];  // already added by BaseJob
}
```

Correct:
```php
protected function bindMiddlewares(): array
{
    return [new RateLimited('api')];  // only add middleware you're adding
}
```
