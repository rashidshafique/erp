<?php

namespace App\Support;

/**
 * The Details toggle of the lists that follow design 28c: on, a row click shows the record in the side panel; off, the panel
 * goes and a row click opens the record. The choice is kept for the session and shared by every list, Livewire or plain page
 * (those post to the lists.details route).
 */
trait ListDetails
{
    public bool $details = true;

    public function mountListDetails(): void
    {
        $this->details = ListLayout::details();
    }

    public function toggleDetails(): void
    {
        $this->details = ! $this->details;
        ListLayout::setDetails($this->details);
    }
}
