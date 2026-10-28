<?php

namespace App\Domains\Enquiry\Console;

use App\Domains\Enquiry\Models\Enquiry;
use App\Notifications\AppNotice;
use App\Services\Notifications\Notify;
use Illuminate\Console\Command;

/** Reminds the owner of a lost or declined enquiry on the follow-up date set when it was closed (design 14c). Safe to repeat. */
class EnquiryFollowUps extends Command
{
    protected $signature = 'enquiries:follow-ups';

    protected $description = 'Remind owners of lost or declined enquiries that are due a follow-up today (safe to repeat)';

    public function handle(Notify $notify): int
    {
        $sent = 0;
        Enquiry::with('owner', 'customer')->whereIn('status', ['LOST', 'DECLINED'])->whereNotNull('follow_up_on')
            ->whereDate('follow_up_on', '<=', today())->whereNull('follow_up_sent_at')
            ->each(function (Enquiry $e) use ($notify, &$sent) {
                $notice = new AppNotice('enquiry-follow-up', "Follow up {$e->code}", ($e->customer?->name ? $e->customer->name.': ' : '').$e->title, 'info', route('enquiries.show', $e));
                $e->owner && $e->owner->is_active ? $e->owner->notify($notice) : $notify->toPermission('sales.manage', $notice);
                $e->update(['follow_up_sent_at' => now()]);
                $sent++;
            });
        $this->info("Sent {$sent} follow-up reminders.");

        return self::SUCCESS;
    }
}
