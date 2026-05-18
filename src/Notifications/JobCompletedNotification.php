<?php

namespace CoreFoundation\Notifications;

use CoreFoundation\Jobs\BaseJob;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\SlackMessage;

class JobCompletedNotification extends Notification
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
            ->content(class_basename($this->job).' Job has completed.');
    }
}
