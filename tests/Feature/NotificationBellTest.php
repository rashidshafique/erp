<?php

namespace Tests\Feature;

use App\Models\Furnace;
use App\Models\Heat;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AppNotice;
use App\Services\Production\FloorAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $roleCode): User
    {
        $user = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', $roleCode)->value('id'));

        return $user;
    }

    private function heat(string $number): Heat
    {
        $furnace = Furnace::firstOrCreate(['code' => 'IF1'], ['name' => 'Induction', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE', 'rated_capacity_kg' => 3000]);

        return Heat::create(['heat_number' => $number, 'furnace_id' => $furnace->id, 'process_type' => 'INDUCTION', 'status' => 'IN_PROGRESS', 'actual_start_at' => now()->subHour()]);
    }

    public function test_notices_are_grouped_by_urgency_and_an_unread_urgent_one_marks_the_badge(): void
    {
        $user = $this->user('PRODUCTION_MANAGER');
        $user->notify(new AppNotice('reorder', 'Coke is low', '', 'warning'));
        $user->notify(new AppNotice('lots-made', 'Two lots to check', '', 'info'));
        $this->actingAs($user);

        Livewire::test('notification-center')->assertDontSee('2 !')->call('toggle')
            ->assertSeeInOrder(['To do', 'Coke is low', 'For information', 'Two lots to check'])->assertDontSee('Urgent', false);

        $user->notify(new AppNotice('charge-overdue', 'Charge 3 is late', '', 'danger'));

        Livewire::test('notification-center')->assertSee('3 !')->call('toggle')
            ->assertSeeInOrder(['Urgent', 'Charge 3 is late', 'To do', 'For information']);
    }

    public function test_mark_all_read_leaves_urgent_notices_unread(): void
    {
        $user = $this->user('PRODUCTION_MANAGER');
        $user->notify(new AppNotice('charge-overdue', 'Charge 3 is late', '', 'danger'));
        $user->notify(new AppNotice('reorder', 'Coke is low', '', 'warning'));
        $user->notify(new AppNotice('lots-made', 'Two lots to check', '', 'success'));
        $this->actingAs($user);

        Livewire::test('notification-center')->call('markAllRead');

        $this->assertSame(['Charge 3 is late'], $user->fresh()->unreadNotifications->pluck('data.title')->all());
    }

    public function test_with_nothing_unread_the_panel_says_so_and_keeps_the_last_read_notices(): void
    {
        $user = $this->user('PRODUCTION_MANAGER');
        $user->notify(new AppNotice('reorder', 'Coke is low', '', 'warning'));
        $user->unreadNotifications->markAsRead();
        $this->actingAs($user);

        Livewire::test('notification-center')->call('toggle')->assertSeeInOrder(["You're up to date", 'Earlier', 'Coke is low']);
    }

    public function test_a_late_charge_notice_settles_once_the_charge_is_skipped_or_the_heat_stops(): void
    {
        $melter = $this->user('MELT_SUPERVISOR');
        $alerts = app(FloorAlerts::class);
        $first = $this->heat('HT-1');
        $second = $this->heat('HT-2');
        $alerts->chargeOverdue($first, 3, 12);
        $alerts->chargeOverdue($second, 1, 15);

        $this->assertSame(0, $alerts->settleCharges(), 'nothing is settled while both charges are still due');

        $first->update(['skipped_charge_rounds' => [3]]);
        $this->assertSame(0, $alerts->settleCharges($second), 'settling one heat leaves the other alone');
        $this->assertSame(1, $alerts->settleCharges($first));

        $second->update(['status' => 'COMPLETED']);
        $this->artisan('heats:warn-overdue-charges')->assertSuccessful();

        $this->assertCount(0, $melter->fresh()->unreadNotifications);
    }
}
