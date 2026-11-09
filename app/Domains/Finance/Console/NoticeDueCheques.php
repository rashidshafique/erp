<?php

namespace App\Domains\Finance\Console;

use App\Domains\Finance\Models\Cheque;
use App\Notifications\AppNotice;
use App\Services\Notifications\Notify;
use App\Support\Formats;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class NoticeDueCheques extends Command
{
    protected $signature = 'finance:due-cheques';

    protected $description = 'Tell finance each morning which post-dated cheques fall due today or are past their date and still not cleared, once a day';

    public function handle(Notify $notify): int
    {
        $due = Cheque::open()->where('cheque_date', '<=', today())->with(['customer', 'supplier'])->orderBy('cheque_date')->get();
        if ($due->isEmpty() || ! Cache::add('due-cheques:'.today()->toDateString(), true, now()->addDay())) {
            $this->info('0 notice(s) sent.');

            return self::SUCCESS;
        }
        $received = $due->where('direction', 'RECEIVED');
        $issued = $due->where('direction', 'ISSUED');
        $line = fn ($set, string $what) => $set->count().' '.$what.' · '.Formats::money((float) $set->sum('amount'), 0);
        $body = collect([
            $received->isNotEmpty() ? $line($received, $received->count() === 1 ? 'cheque to deposit' : 'cheques to deposit') : null,
            $issued->isNotEmpty() ? $line($issued, $issued->count() === 1 ? 'cheque we gave can be presented' : 'cheques we gave can be presented') : null,
        ])->filter()->implode('; ').'.';
        $told = $notify->toPermissions(['finance.manage'], new AppNotice(
            'cheques-due',
            $due->count() === 1 ? 'A post-dated cheque is due' : $due->count().' post-dated cheques are due',
            $body,
            'warning',
            route($received->isNotEmpty() ? 'cheques.received' : 'cheques.issued'),
        ));
        $this->info("{$told} notice(s) sent.");

        return self::SUCCESS;
    }
}
