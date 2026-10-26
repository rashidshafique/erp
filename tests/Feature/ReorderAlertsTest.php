<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AppNotice;
use App\Services\Notifications\Notify;
use App\Services\Stock\ReorderWatcher;
use App\Services\Stock\StockLedger;
use App\Services\Stock\StockLevels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReorderAlertsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $roleCode, bool $active = true): User
    {
        $user = User::factory()->create(['is_active' => $active, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', $roleCode)->value('id'));

        return $user;
    }

    private function material(array $over = []): Material
    {
        return Material::create($over + ['code' => 'M-'.uniqid(), 'name' => 'Ferro silicon', 'material_type' => 'ALLOY', 'base_uom' => 'kg', 'status' => 'ACTIVE',
            'is_stock_tracked' => true, 'requires_lot_tracking' => true, 'minimum_stock_qty' => 100, 'reorder_qty' => 500]);
    }

    private function lotWith(Material $material, float $quantity): MaterialLot
    {
        $lot = MaterialLot::create(['lot_number' => 'L-'.uniqid(), 'material_id' => $material->id, 'status' => 'ACCEPTED', 'received_qty' => $quantity, 'accepted_qty' => $quantity, 'unit_cost' => 90, 'currency_code' => 'PKR']);
        app(StockLedger::class)->receive($lot, $quantity, 90, 'kg');

        return $lot;
    }

    public function test_only_watched_materials_at_or_below_minimum_need_reordering_with_a_suggested_quantity(): void
    {
        $low = $this->material(['code' => 'LOW']);
        $this->lotWith($low, 60);
        $out = $this->material(['code' => 'OUT', 'reorder_qty' => null]);
        $fine = $this->material(['code' => 'FINE']);
        $this->lotWith($fine, 400);
        $unwatched = $this->material(['code' => 'NOMIN', 'minimum_stock_qty' => null]);
        $zeroMin = $this->material(['code' => 'ZEROMIN', 'minimum_stock_qty' => 0]);
        $inactive = $this->material(['code' => 'OLD', 'status' => 'INACTIVE']);

        $rows = app(StockLevels::class)->needingReorder();

        $this->assertSame(['OUT', 'LOW'], $rows->pluck('material.code')->all());   // out of stock first
        $this->assertSame(500.0, $rows->firstWhere('material.code', 'LOW')['suggested']);   // the material's reorder quantity
        $this->assertSame(100.0, $rows->firstWhere('material.code', 'OUT')['suggested']);   // none set: back up to the minimum
        $this->assertTrue($rows->firstWhere('material.code', 'OUT')['out']);
    }

    public function test_falling_to_the_minimum_tells_everyone_who_manages_stores_once_and_recovery_rearms_it(): void
    {
        $stores = $this->user('STORES_DISPATCH');
        $admin = $this->user('ADMIN');
        $viewer = $this->user('VIEWER');
        $gone = $this->user('STORES_DISPATCH', false);
        $material = $this->material();
        $lot = $this->lotWith($material, 150);

        $ledger = app(StockLedger::class);
        $ledger->issue($lot, 30, 'kg');   // 120: still above the minimum
        $this->assertSame(0, $stores->notifications()->count());

        $ledger->issue($lot, 30, 'kg');   // 90: at or below
        $ledger->issue($lot, 10, 'kg');   // 80: already alerted
        $this->assertSame(1, $stores->notifications()->count());
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame(0, $viewer->notifications()->count());
        $this->assertSame(0, $gone->notifications()->count());
        $notice = $stores->notifications()->first()->data;
        $this->assertSame('reorder', $notice['kind']);
        $this->assertSame('warning', $notice['tone']);
        $this->assertStringContainsString('at its minimum', $notice['title']);
        $this->assertSame(route('stock-reorder.index'), $notice['url']);
        $this->assertNotNull($material->fresh()->reorder_alerted_at);

        app(StockLedger::class)->receive($lot, 200, 90, 'kg');   // 280: back above, flag cleared
        $this->assertNull($material->fresh()->reorder_alerted_at);

        $ledger->issue($lot, 200, 'kg');   // 80 again: alerts again
        $this->assertSame(2, $stores->notifications()->count());
    }

    public function test_running_out_is_a_danger_notice_and_the_hourly_check_catches_a_changed_minimum(): void
    {
        $stores = $this->user('STORES_DISPATCH');
        $material = $this->material(['minimum_stock_qty' => null]);
        $lot = $this->lotWith($material, 50);

        $this->assertSame(0, app(ReorderWatcher::class)->checkAll());   // no minimum: not watched

        $material->update(['minimum_stock_qty' => 100]);
        $this->artisan('stock:check-reorder')->expectsOutput('1 reorder notice(s) raised.')->assertSuccessful();
        $this->artisan('stock:check-reorder')->expectsOutput('0 reorder notice(s) raised.')->assertSuccessful();

        app(StockLedger::class)->issue($lot, 50, 'kg');
        $material->update(['minimum_stock_qty' => 120]);   // still flagged, so no repeat
        $this->assertSame(1, $stores->notifications()->count());
    }

    public function test_a_notice_can_go_to_named_roles(): void
    {
        $sales = $this->user('SALES');
        $stores = $this->user('STORES_DISPATCH');

        $sent = app(Notify::class)->toRoles(['SALES'], new AppNotice('general', 'Hello', 'Body', 'success'));

        $this->assertSame(1, $sent);
        $this->assertSame('Hello', $sales->notifications()->first()->data['title']);
        $this->assertSame(0, $stores->notifications()->count());
    }

    public function test_the_bell_counts_unread_notices_marks_them_read_and_opens_the_page(): void
    {
        $user = $this->user('STORES_DISPATCH');
        $other = $this->user('STORES_DISPATCH');
        $user->notify(new AppNotice('reorder', 'Alloy low', 'Order more', 'warning', '/stock-report'));
        $user->notify(new AppNotice('reorder', 'Sand low', '', 'danger'));
        $other->notify(new AppNotice('reorder', 'Not yours'));

        $this->actingAs($user);
        $bell = Livewire::test('notification-center');
        $bell->assertSeeHtml('<span class="notice-badge">2</span>')->call('toggle');
        $bell->assertSee('Alloy low')->assertDontSee('Not yours')->assertSeeHtml('<span class="notice-badge">2</span>');

        $first = $user->notifications->first(fn ($n) => $n->data['title'] === 'Alloy low');
        $bell->call('openNotice', $first->id)->assertRedirect('/stock-report');
        $this->assertNotNull($first->fresh()->read_at);
        $this->assertSame(1, $user->unreadNotifications()->count());

        $bell->call('markAllRead');
        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertNull($other->notifications()->first()->read_at);
    }

    public function test_a_notice_that_arrives_after_the_page_loaded_pops_up_once(): void
    {
        $user = $this->user('STORES_DISPATCH');
        $this->actingAs($user);
        $user->notify(new AppNotice('general', 'Before the page loaded'));
        $this->travel(2)->seconds();

        $bell = Livewire::test('notification-center');
        $bell->call('pollForNew')->assertNotDispatched('app-toast');

        $this->travel(2)->seconds();
        $user->notify(new AppNotice('reorder', 'Fresh one', 'Details', 'danger', '/x'));
        $bell->call('pollForNew')->assertDispatched('app-toast', fn ($event, $params) => $params['notices'][0]['title'] === 'Fresh one' && $params['notices'][0]['tone'] === 'danger');
        $bell->call('pollForNew')->assertNotDispatched('app-toast');
    }

    public function test_the_reorder_page_lists_materials_and_the_dashboard_flags_them(): void
    {
        $this->material(['code' => 'LOWMAT', 'name' => 'Low alloy']);
        $this->material(['code' => 'OKMAT', 'name' => 'Plenty']);
        $this->lotWith(Material::where('code', 'OKMAT')->first(), 900);

        $this->get(route('stock-reorder.index'))->assertOk();
        Livewire::test('inventory::reorder-alerts')->assertSee('LOWMAT')->assertDontSee('OKMAT')
            ->assertSee(route('materials.lots.create', Material::where('code', 'LOWMAT')->first()))->set('search', 'zzz')->assertDontSee('LOWMAT')
            ->set('search', '')->call('checkNow')->assertSee('Checked');

        $this->get(route('dashboard', ['layout' => 'command']))->assertOk()->assertSee('1 material is at or below minimum stock', false);
    }

    public function test_the_bell_sits_in_the_top_bar_for_a_signed_in_user_and_not_for_a_guest(): void
    {
        $this->get(route('dashboard'))->assertOk()->assertDontSee('notice-center');

        $this->actingAs($this->user('ADMIN'))->get(route('dashboard'))->assertOk()
            ->assertSee('class="notice-center"', false)->assertSee('toasts: Array()', false);
    }
}
