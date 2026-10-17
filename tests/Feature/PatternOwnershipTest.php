<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Customer;
use App\Models\MouldingRun;
use App\Models\Pattern;
use App\Models\PatternOwnershipEvent;
use App\Models\PatternRevision;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductRevision;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\User;
use App\Services\Patterns\PatternOwnership;
use App\Support\PatternOwnershipRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PatternOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private Customer $acme;

    private Customer $other;

    private Pattern $pattern;

    private PatternRevision $revision;

    private ProductRevision $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->acme = Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps']);
        $this->other = Customer::create(['code' => 'OTH', 'name' => 'Other Co']);
        $this->product = ProductRevision::create(['product_id' => Product::create(['code' => 'P-1', 'name' => 'Pump body'])->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $this->pattern = Pattern::create(['code' => 'PT-1', 'name' => 'Pump plate']);
        $this->revision = PatternRevision::create(['pattern_id' => $this->pattern->id, 'status' => 'APPROVED', 'cavities_per_mould' => 1]);
        $this->revision->cavityMap()->create(['cavity_number' => 1, 'product_revision_id' => $this->product->id]);
    }

    /** A released production order for the customer, or a stock order when no customer is given. */
    private function order(?Customer $customer): ProductionOrder
    {
        $line = null;
        if ($customer) {
            $so = SalesOrder::create(['order_number' => 'SO-'.uniqid(), 'customer_id' => $customer->id, 'order_date' => today(), 'status' => 'CONFIRMED']);
            $line = SalesOrderLine::create(['sales_order_id' => $so->id, 'product_id' => $this->product->product_id, 'product_revision_id' => $this->product->id, 'line_number' => 1, 'ordered_qty' => 50, 'uom' => 'piece']);
        }

        return ProductionOrder::create(['production_order_number' => 'PO-'.uniqid(), 'product_id' => $this->product->product_id, 'product_revision_id' => $this->product->id,
            'sales_order_line_id' => $line?->id, 'order_type' => $customer ? 'CUSTOMER' : 'STOCK', 'status' => 'RELEASED', 'planned_qty' => 50, 'uom' => 'piece']);
    }

    private function ownedBy(Customer $customer, string $type = 'CUSTOMER'): void
    {
        $this->pattern->update(['owner_type' => $type, 'owner_customer_id' => $customer->id]);
    }

    private function runFor(ProductionOrder $order)
    {
        return $this->post(route('moulding-runs.store'), ['production_order_id' => $order->id, 'pattern_revision_id' => $this->revision->id, 'run_date' => today()->toDateString(),
            'status' => 'COMPLETED', 'moulds_made_qty' => 2, 'accepted_mould_qty' => 2, 'damaged_mould_qty' => 0]);
    }

    public function test_owner_and_custody_changes_are_saved_with_history(): void
    {
        $this->put(route('patterns.ownership', $this->pattern), ['owner_type' => 'CUSTOMER', 'custody' => 'IN_STORE'])->assertSessionHasErrors('owner_customer_id');
        $this->put(route('patterns.ownership', $this->pattern), ['owner_type' => 'FOUNDRY', 'custody' => 'LENT'])->assertSessionHasErrors('custody_location');

        $this->put(route('patterns.ownership', $this->pattern), ['owner_type' => 'CUSTOMER', 'owner_customer_id' => $this->acme->id, 'supplied_by_customer' => 1,
            'insured_value' => 250000, 'custody' => 'LENT', 'custody_location' => 'Star Castings', 'event_reference' => 'GP-77'])->assertSessionHasNoErrors();

        $pattern = $this->pattern->fresh();
        $this->assertSame(['CUSTOMER', $this->acme->id, true, 'LENT'], [$pattern->owner_type, $pattern->owner_customer_id, $pattern->supplied_by_customer, $pattern->custody]);
        $this->assertSame([['OWNER_CHANGED', 'Foundry', 'Acme Pumps'], ['CUSTODY_CHANGED', 'In our store', 'Lent to another supplier · Star Castings']],
            PatternOwnershipEvent::orderBy('event_type', 'desc')->get()->map(fn ($e) => [$e->event_type, $e->from_value, $e->to_value])->all());
        $this->assertSame('GP-77', PatternOwnershipEvent::where('event_type', 'CUSTODY_CHANGED')->value('reference'));
        $this->get(route('patterns.show', $pattern))->assertOk()->assertSee('Property of Acme Pumps')->assertSee('Star Castings');
        $this->get(route('patterns.index', ['owner' => $this->acme->id]))->assertOk()->assertSee('PT-1');
        $this->get(route('patterns.index', ['owner' => 'FOUNDRY']))->assertOk()->assertDontSee('PT-1');
        $this->get(route('labels.show', ['pattern', $pattern->id]))->assertOk()->assertSee('Property of Acme Pumps');
        $this->get(route('customers.index'))->assertOk()->assertSee(route('patterns.index', ['owner' => $this->acme->id]), false);
        $this->get(route('products.show', $this->product->product_id))->assertOk()->assertSee('Owner: Acme Pumps');
    }

    public function test_a_customers_pattern_is_blocked_on_other_orders_by_default(): void
    {
        $this->ownedBy($this->acme);

        $this->runFor($this->order($this->other))->assertSessionHasErrors(['pattern_revision_id' => 'Pattern PT-1 belongs to Acme Pumps; order '.ProductionOrder::latest('created_at')->value('production_order_number').' is for another customer. Their pattern may only make their castings (pattern ownership rules).']);
        $this->runFor($this->order(null))->assertSessionHasErrors('pattern_revision_id');
        $this->assertSame(0, MouldingRun::count());

        $this->runFor($this->order($this->acme))->assertSessionHasNoErrors();
        $this->assertSame(1, MouldingRun::count());
    }

    public function test_the_rule_can_warn_or_allow(): void
    {
        $this->ownedBy($this->acme);

        PatternOwnershipRules::set('other_customer', 'WARN');
        $this->runFor($this->order(null))->assertSessionHasNoErrors()->assertSessionHas('warning', fn ($w) => str_contains($w, 'belongs to Acme Pumps') && str_contains($w, 'a stock order'));

        PatternOwnershipRules::set('other_customer', 'ALLOW');
        $this->runFor($this->order($this->other))->assertSessionHasNoErrors()->assertSessionMissing('warning');
        $this->assertSame(2, MouldingRun::count());
    }

    public function test_bulk_entry_and_work_lists_refuse_another_customers_pattern(): void
    {
        $this->ownedBy($this->acme);
        $order = $this->order($this->other);

        $this->post(route('moulding-runs.bulk.save'), ['rows' => [['run_date' => today()->toDateString(), 'pattern_id' => $this->pattern->id, 'pattern_revision_id' => $this->revision->id,
            'production_order_id' => $order->id, 'moulds_made_qty' => 3]]])->assertSessionHasErrors('rows.0.pattern_id');
        $this->expectException(ValidationException::class);
        app(PatternOwnership::class)->checkUse($this->revision, $order);
    }

    public function test_returning_a_pattern_archives_it_and_stops_moulding(): void
    {
        $this->post(route('patterns.return', $this->pattern), ['reference' => 'DN-1'])->assertSessionHasErrors('reference');   // the foundry's own
        $this->ownedBy($this->acme);
        $this->delete(route('patterns.archive', $this->pattern))->assertSessionHasErrors('archive');

        $this->post(route('patterns.return', $this->pattern), ['reference' => 'DN-12'])->assertSessionHasNoErrors();

        $pattern = Pattern::withTrashed()->find($this->pattern->id);
        $this->assertTrue($pattern->trashed());
        $this->assertSame('RETURNED', $pattern->custody);
        $this->assertSame('DN-12', PatternOwnershipEvent::where('event_type', 'RETURNED')->value('reference'));
        $this->expectException(ValidationException::class);
        MouldingRun::create(['run_number' => 'MR-X', 'production_order_id' => $this->order($this->acme)->id, 'pattern_revision_id' => $this->revision->id, 'run_date' => today(), 'status' => 'COMPLETED', 'moulds_made_qty' => 1, 'accepted_mould_qty' => 1]);
    }

    public function test_scrapping_a_customers_pattern_needs_consent_and_an_approval(): void
    {
        $this->ownedBy($this->acme);
        $maker = $this->user();
        $checker = $this->user();

        $this->actingAs($maker)->post(route('patterns.scrap', $this->pattern), [])->assertSessionHasErrors('reference');
        $this->actingAs($maker)->post(route('patterns.scrap', $this->pattern), ['reference' => 'ACME letter 4/26'])->assertSessionHas('warning', fn ($w) => str_contains($w, 'needs approval'));
        $this->assertSame('IN_STORE', $this->pattern->fresh()->custody);

        $request = ApprovalRequest::where('action_key', 'pattern.scrap_customer_pattern')->sole();
        $this->actingAs($checker)->post(route('approvals.decide', $request), ['decision' => 'APPROVE'])->assertSessionHasNoErrors();
        $this->actingAs($maker)->post(route('patterns.scrap', $this->pattern), ['reference' => 'ACME letter 4/26'])->assertSessionHasNoErrors();

        $pattern = Pattern::withTrashed()->find($this->pattern->id);
        $this->assertSame(['SCRAPPED', true], [$pattern->custody, $pattern->trashed()]);
        $this->post(route('patterns.restore', $pattern))->assertSessionHasErrors('restore');
    }

    public function test_the_foundry_scraps_its_own_pattern_without_an_approval(): void
    {
        $this->post(route('patterns.scrap', $this->pattern), ['notes' => 'Worn out'])->assertSessionHasNoErrors();

        $this->assertSame('SCRAPPED', Pattern::withTrashed()->find($this->pattern->id)->custody);
        $this->assertSame(0, ApprovalRequest::count());
    }

    public function test_a_pattern_being_paid_off_passes_when_recorded(): void
    {
        $this->post(route('patterns.pay-off', $this->pattern))->assertSessionHasErrors('pay_off');
        $this->put(route('patterns.ownership', $this->pattern), ['owner_type' => 'PAYING_OFF', 'owner_customer_id' => $this->acme->id, 'custody' => 'IN_STORE'])->assertSessionHasErrors('amortise_qty');
        $this->put(route('patterns.ownership', $this->pattern), ['owner_type' => 'PAYING_OFF', 'owner_customer_id' => $this->acme->id, 'amortise_qty' => 500, 'custody' => 'IN_STORE'])->assertSessionHasNoErrors();
        $this->get(route('patterns.show', $this->pattern))->assertOk()->assertSee('0 of 500 castings dispatched', false);

        $this->assertSame(0, app(PatternOwnership::class)->dispatchedTo($this->acme->id), 'manual by default');
        $this->post(route('patterns.pay-off', $this->pattern), ['reference' => 'INV-9'])->assertSessionHasNoErrors();

        $this->assertSame('CUSTOMER', $this->pattern->fresh()->owner_type);
        $event = PatternOwnershipEvent::where('event_type', 'PAID_OFF')->sole();
        $this->assertSame(['Acme Pumps (paying off)', 'Acme Pumps', 'INV-9'], [$event->from_value, $event->to_value, $event->reference]);
    }

    public function test_quotes_charge_no_tooling_on_the_customers_own_pattern(): void
    {
        $ownership = app(PatternOwnership::class);
        $this->assertFalse($ownership->toolingFor($this->acme->id, $this->product->product_id)['customer_owned']);

        $this->ownedBy($this->acme);
        $this->assertTrue($ownership->toolingFor($this->acme->id, $this->product->product_id)['customer_owned']);
        $forOther = $ownership->toolingFor($this->other->id, $this->product->product_id);
        $this->assertSame([false, true], [$forOther['customer_owned'], $forOther['warning']]);
        $this->assertStringContainsString('belongs to Acme Pumps and may not make castings for this customer', $forOther['note']);
    }

    public function test_idle_patterns_are_listed_with_storage_charges_when_charged(): void
    {
        $this->ownedBy($this->acme);
        $this->pattern->update(['acquired_on' => today()->subMonths(20)]);
        Pattern::create(['code' => 'PT-NEW', 'name' => 'New plate', 'acquired_on' => today()->subMonth()]);

        $this->get(route('pattern-ownership.index'))->assertOk()->assertSee('PT-1')->assertDontSee('PT-NEW')->assertDontSee('Storage due');

        $this->put(route('settings.pattern-ownership'), ['other_customer' => 'BLOCK', 'pay_off' => 'MANUAL', 'idle_months' => 12, 'storage' => 'CHARGE', 'storage_free_months' => 12, 'storage_rate' => 1000])->assertSessionHasNoErrors();

        $row = app(PatternOwnership::class)->idle()->sole();
        $this->assertSame([20, 8000.0], [$row['months'], $row['charge']]);
        $this->get(route('pattern-ownership.index'))->assertOk()->assertSee('Storage due')->assertSee('8,000');
    }

    private function user(): User
    {
        $user = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', 'QUALITY_MANAGER')->value('id'));

        return $user;
    }
}
