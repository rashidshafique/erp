<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * A notice for the bell and the pop-up toasts: a short title and line, how urgent it is, and where to go. Any part of the app
 * raises one through {@see \App\Services\Notifications\Notify}. `kind` groups notices of one sort, such as `reorder`.
 */
class AppNotice extends Notification
{
    public const TONES = ['info', 'success', 'warning', 'danger'];

    public function __construct(
        public string $kind,
        public string $title,
        public string $body = '',
        public string $tone = 'info',
        public ?string $url = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, string|null> */
    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind, 'title' => $this->title, 'body' => $this->body, 'tone' => in_array($this->tone, self::TONES, true) ? $this->tone : 'info', 'url' => $this->url];
    }
}
