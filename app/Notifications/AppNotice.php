<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * A notice for the bell and the pop-up toasts: a short title and line, how urgent it is, and where to go. Any part of the app
 * raises one through {@see \App\Services\Notifications\Notify}. `kind` groups notices of one sort, such as `reorder`;
 * `ref` names the thing it is about (such as `heat:<id>:<round>`), so the notice can be settled once that thing is dealt with.
 * `link` names the screen the url opens ("Charge tab"), shown as "→ Charge tab" under the notice. `for` lists the hats a person
 * got it for when they hold more than one, such as Melt shop and Planning.
 */
class AppNotice extends Notification
{
    public const TONES = ['info', 'success', 'warning', 'danger'];

    /** @param  list<string>  $for */
    public function __construct(
        public string $kind,
        public string $title,
        public string $body = '',
        public string $tone = 'info',
        public ?string $url = null,
        public ?string $ref = null,
        public ?string $link = null,
        public array $for = [],
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** The screen a notice of each kind opens, for notices raised without their own link name. */
    public const LINKS = [
        'reorder' => 'Reorder list', 'test-result' => 'Test result', 'inspection' => 'Inspection',
        'incoming' => 'Incoming lot', 'enquiry-follow-up' => 'Enquiry', 'charge-overdue' => 'Charge tab',
        'list-issued' => 'Moulding list', 'list-not-issued' => 'Moulding list', 'moulds-counted' => 'Moulds ready',
        'moulds-short' => 'Remake needed', 'heat-started' => 'Count', 'lots-made' => 'Lots queue', 'heat-unaccounted' => 'Metal balance',
        'cheques-due' => 'Cheques register',
    ];

    /** The "→ …" line under a stored notice, or null when it opens nothing. @param array<string, mixed> $data */
    public static function linkLabel(array $data): ?string
    {
        if (empty($data['url'])) {
            return null;
        }

        return $data['link'] ?? self::LINKS[$data['kind'] ?? ''] ?? 'Open';
    }

    /** A copy of this notice labelled with the hats it is sent for. @param list<string> $for */
    public function forHats(array $for): self
    {
        $copy = clone $this;
        $copy->for = $for;

        return $copy;
    }

    /** @return array<string, string|list<string>|null> */
    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind, 'title' => $this->title, 'body' => $this->body, 'tone' => in_array($this->tone, self::TONES, true) ? $this->tone : 'info', 'url' => $this->url]
            + ($this->ref !== null ? ['ref' => $this->ref] : [])
            + ($this->link !== null ? ['link' => $this->link] : [])
            + (count($this->for) > 1 ? ['for' => $this->for] : []);
    }
}
