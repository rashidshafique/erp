<?php

use App\Support\Uploads;

/** Only what differs from Laravel's own wording; the rest comes from the framework. */
return [
    'uploaded' => fn () => Uploads::failedMessage(),
];
