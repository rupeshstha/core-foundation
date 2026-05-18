<?php

namespace CoreFoundation\Notifications;

use CoreFoundation\Jobs\BaseJob;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class JobStartedNotification extends Notification
{
    public function __construct(
        protected BaseJob $job,
    ) {}

    public function via(object $notifiable): array
    {
        return ['slack'];
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->info()
            ->content(class_basename($this->job) . ' Job has started.');
    }
}
