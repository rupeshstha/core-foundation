<?php

namespace CoreFoundation\Notifications;

use Throwable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\SlackMessage;

class JobFailedNotification extends Notification
{
    public function __construct(
        protected Throwable $exception
    ) {}

    public function via(object $notifiable): array
    {
        return ['slack'];
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->error()
            ->content('Job failed: ' . $this->exception->getMessage());
    }
}
