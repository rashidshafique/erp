<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Furnace;
use App\Models\IronGrade;
use App\Models\Pattern;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Support\MeltingCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MeltingCalendarTest extends TestCase
{
    use RefreshDatabase;

    /** Every other day from Monday 19 Oct 2026, never on Friday: 19, 21, (23 is a Friday), 25, 27, 29, 31, 2 Nov, 4 Nov. */
    private function everyOtherDayExceptFriday(int $afterPour = 1): void
    {
        MeltingCalendar::save(['mode' => 'INTERVAL', 'weekdays' => [], 'interval' => 2, 'anchor' => '2026-10-19', 'skip' => [5], 'after_pour_days' => $afterPour]);
    }

    private function days(array $days): array
    {
        return array_map(fn (Carbon $d) => $d->format('D j'), $days);
    }

    public function test_without_a_rule_every_day_counts_and_nothing_is_checked(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 09:00'));

        $this->assertFalse(MeltingCalendar::enabled());
        $this->assertSame('2026-10-22', MeltingCalendar::next()->toDateString());
        $this->assertTrue(MeltingCalendar::isCampaignDay(Carbon::parse('2026-10-23')));   // a Friday
        $this->assertStringContainsString('every day', MeltingCalendar::describe());
    }

    public function test_every_other_day_except_friday_skips_the_friday_and_keeps_counting_from_the_anchor(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 09:00'));
        $this->everyOtherDayExceptFriday();

        $this->assertSame(['Sun 25', 'Tue 27', 'Thu 29', 'Sat 31', 'Mon 2', 'Wed 4'], $this->days(MeltingCalendar::upcoming(6)));
        $this->assertFalse(MeltingCalendar::isCampaignDay(Carbon::parse('2026-10-23')), 'The 23rd is on the every-other-day count but is a Friday.');
        $this->assertTrue(MeltingCalendar::isCampaignDay(Carbon::parse('2026-10-19')));
        $this->assertFalse(MeltingCalendar::isCampaignDay(Carbon::parse('2026-10-20')));
        $this->assertSame('The furnace is lit every other day from Mon 19 Oct, never on Friday.', MeltingCalendar::describe());
    }

    public function test_chosen_weekdays_are_the_campaign_days(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 09:00'));   // a Thursday
        MeltingCalendar::save(['mode' => 'WEEKDAYS', 'weekdays' => [1, 3, 6], 'interval' => 2, 'anchor' => null, 'skip' => [], 'after_pour_days' => 0]);

        $this->assertSame(['Sat 24', 'Mon 26', 'Wed 28'], $this->days(MeltingCalendar::upcoming(3)));
        $this->assertSame('The furnace is lit on Monday, Wednesday, Saturday.', MeltingCalendar::describe());
    }

    public function test_a_due_date_is_met_only_when_a_melting_day_falls_between_today_and_the_last_pouring_day(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 09:00'));   // Thursday; the next melting day is Sunday 25th
        $this->everyOtherDayExceptFriday(afterPour: 1);

        $short = MeltingCalendar::checkDue(Carbon::parse('2026-10-24'));   // pour by Friday 23rd: no melting day
        $enough = MeltingCalendar::checkDue(Carbon::parse('2026-10-26'));  // pour by Sunday 25th: the 25th will do
        $this->assertSame([false, '2026-10-23', '2026-10-25'], [$short['ok'], $short['latest_pour']->toDateString(), $short['next']->toDateString()]);
        $this->assertTrue($enough['ok']);

        $this->everyOtherDayExceptFriday(afterPour: 0);
        $this->assertTrue(MeltingCalendar::checkDue(Carbon::parse('2026-10-25'))['ok'], 'With no days needed after the pour, pouring on the due date is in time.');
    }

    public function test_the_settings_page_saves_and_explains_the_rule_and_refuses_an_incomplete_one(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 09:00'));

        $this->put(route('settings.melting-days'), ['mode' => 'INTERVAL', 'interval' => 2, 'anchor' => '2026-10-19', 'skip' => [5], 'after_pour_days' => 1])->assertSessionHasNoErrors();
        $this->get(route('settings.melting-days.edit'))->assertOk()->assertSee('every other day from Mon 19 Oct, never on Friday')->assertSee('Sun 25 Oct');

        $this->put(route('settings.melting-days'), ['mode' => 'INTERVAL', 'interval' => 2, 'anchor' => '', 'after_pour_days' => 1])->assertSessionHasErrors('anchor');
        $this->put(route('settings.melting-days'), ['mode' => 'WEEKDAYS', 'after_pour_days' => 1])->assertSessionHasErrors('weekdays');
        $this->put(route('settings.melting-days'), ['mode' => 'INTERVAL', 'interval' => 2, 'anchor' => '2026-10-19', 'skip' => [0, 1, 2, 3, 4, 5, 6], 'after_pour_days' => 1])->assertSessionHasErrors('skip');
        $this->assertSame('INTERVAL', MeltingCalendar::settings()['mode'], 'A refused change leaves the saved rule alone.');
    }

    private function openOrder(string $due): ProductionOrder
    {
        $customer = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);
        Furnace::create(['code' => 'IF1', 'name' => 'Induction 1', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE', 'rated_capacity_kg' => 1000, 'nominal_melting_rate_kg_per_hour' => 500]);
        $product = Product::create(['code' => 'PUMP-1', 'name' => 'Pump body']);

        $this->post(route('quick.orders.store'), ['customer_id' => $customer->id, 'due_date' => $due, 'lines' => [
            ['product_id' => $product->id, 'qty' => 10, 'iron_grade_id' => IronGrade::where('code', 'EN-GJL-200')->value('id'), 'casting_weight_kg' => 6],
        ]])->assertSessionHasNoErrors();

        return ProductionOrder::firstOrFail();
    }

    public function test_the_quick_order_warns_when_no_melting_day_falls_before_the_due_date_but_keeps_the_order(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 09:00'));
        $this->everyOtherDayExceptFriday(afterPour: 1);

        $this->get(route('quick.orders.create'))->assertOk()->assertSee('Next melting day: Sun 25 Oct');

        $late = Carbon::parse('2026-10-24')->toDateString();   // the 25th is the next melting day, after the last pouring day (the 23rd)
        $this->post(route('quick.orders.store'), ['new_customer' => 'Late Ltd', 'due_date' => $late, 'lines' => [
            ['product_id' => ($p = Product::create(['code' => 'PUMP-2', 'name' => 'Pump 2']))->id, 'qty' => 5, 'iron_grade_id' => IronGrade::where('code', 'EN-GJL-200')->value('id'), 'casting_weight_kg' => 5],
        ]])->assertSessionHas('warning', fn ($m) => str_contains($m, 'PUMP-2 due Sat 24 Oct (pour by Fri 23 Oct)') && str_contains($m, 'Next melting day: Sun 25 Oct'));
        $this->assertSame(1, ProductionOrder::where('product_id', $p->id)->count());

        $this->post(route('quick.orders.store'), ['customer_id' => Customer::value('id'), 'due_date' => '2026-10-29', 'lines' => [
            ['product_id' => $p->id, 'qty' => 5, 'iron_grade_id' => '', 'casting_weight_kg' => ''],
        ]])->assertSessionHasNoErrors()->assertSessionMissing('warning');
    }

    public function test_the_next_heat_page_shows_the_next_melting_day_and_flags_orders_that_cannot_be_poured_in_time(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 09:00'));
        $this->openOrder('2026-10-24');
        $this->get(route('quick.heat.index'))->assertOk()->assertSee('Next melting day:')->assertDontSee('Pour in time?');   // no rule yet: nothing is checked

        $this->everyOtherDayExceptFriday(afterPour: 1);
        $this->get(route('quick.heat.index'))->assertOk()->assertSee('Sun 25 Oct')->assertSee('Pour in time?')->assertSee('No melting day in time (next Sun 25 Oct)');

        ProductionOrder::firstOrFail()->update(['required_date' => '2026-10-28']);
        $this->get(route('quick.heat.index'))->assertOk()->assertSee('Next pour Sun 25 Oct')->assertDontSee('No melting day in time');
    }

    public function test_the_new_customer_help_page_ticks_each_step_from_what_the_customer_has(): void
    {
        $this->get(route('help.new-customer'))->assertOk()->assertSee('New customer checklist')->assertSee('Which way to do it')->assertSee('creates no casting lots')->assertDontSee('@include', false);

        $order = $this->openOrder('2026-12-01');
        $customer = Customer::firstOrFail();
        $product = $order->product;

        $page = $this->get(route('help.new-customer', ['customer' => $customer->id]))->assertOk()->assertSee('Product ordered by Acme Pumps')->assertSee('PUMP-1');
        $this->assertSame(1, substr_count($page->getContent(), 'status-cancelled'), 'Only the missing pattern link shows as missing.');
        $page->assertSee('EN-GJL-200')->assertSee('10.0000 kg')->assertSee('Released');

        $pattern = Pattern::create(['code' => 'P-0900', 'name' => 'Flaskless plate', 'pattern_type' => 'VERTICAL_PLATES', 'status' => 'ACTIVE', 'owner_type' => 'CUSTOMER', 'owner_customer_id' => $customer->id]);
        $pattern->revisions()->create(['revision' => '0', 'status' => 'APPROVED', 'cavities_per_mould' => 2])->cavityMap()->create(
            ['cavity_number' => 1, 'product_revision_id' => $product->revisions()->value('id'), 'cavity_role' => 'WHOLE', 'pieces_per_product' => 1]);
        file_put_contents(storage_path('help.html'), $this->get(route('help.new-customer', ['customer' => $customer->id]))->getContent());
        $this->get(route('help.new-customer', ['customer' => $customer->id]))->assertOk()->assertSee('Flaskless plate')->assertSee("Customer's", false)->assertDontSee('status-cancelled', false);
    }
}
