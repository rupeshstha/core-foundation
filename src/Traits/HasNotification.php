<?php

namespace CoreFoundation\Traits;

use Override;
use Illuminate\Support\Facades\Notification;

trait HasNotification
{
    #[Override]
    protected function shouldNotify(bool $notify = true): bool
    {
        return $notify;
    }

    #[Override]
    protected function notifiables(): array|object|null
    {
        return null;
    }

    /**
     * Core notification dispatcher.
     *
     * Three guards must all pass before anything is sent:
     *   1. shouldNotify() returns true
     *   2. The lifecycle factory returns a non-null Notification
     *   3. notifiables() resolves to a non-empty array
     *
     * Private so child classes can never bypass the shouldNotify() gate.
     *
     * @param  callable(): (?Notification)  $notificationFactory
     */
    private function dispatchNotification(callable $notificationFactory): void
    {
        if (! $this->shouldNotify()) {
            return;
        }

        $notification = $notificationFactory();

        if ($notification === null) {
            return;
        }

        $notifiables = $this->notifiables();
        $notifiables = is_array($notifiables) ? $notifiables : [$notifiables];

        if (empty($notifiables)) {
            return;
        }

        Notification::send($notifiables, $notification);
    }
}
