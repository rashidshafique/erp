<?php

namespace App\Services\Production;

use Carbon\CarbonInterface;

/** Numbers a heat: H-10000, H-10001… one series, from DocumentNumbers. Call inside a transaction. */
class HeatNumber
{
    public function next(CarbonInterface $day): string
    {
        return \App\Support\DocumentNumbers::next('heat');
    }
}
