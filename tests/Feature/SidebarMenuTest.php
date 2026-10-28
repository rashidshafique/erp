<?php

namespace Tests\Feature;

use App\Domains\Capa\Models\CapaAction;
use App\Domains\Purchasing\Models\PurchaseOrder;
use App\Domains\Purchasing\Models\Supplier;
use App\Domains\Sales\Models\SalesOrder;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductRevision;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_has_no_add_new_items_and_a_width_handle(): void
    {
        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $nav = substr($html, strpos($html, '<nav class="sidebar-nav"'), strpos($html, '</nav>') - strpos($html, '<nav class="sidebar-nav"'));
        foreach (['New production order', 'New sales order', 'New request (guided)', 'Record thermal test'] as $t) {
            $this->assertStringNotContainsString($t, $nav);
        }
        $this->assertStringContainsString('id="sidebar-resizer"', $html);
        $this->assertStringContainsString('erp.menuWidth', $html);
        // The index pages still carry the add buttons.
        $this->get(route('sales-orders.index'))->assertSee('New sales order');
    }

    public function test_selected_item_has_no_background(): void
    {
        $this->get(route('dashboard'))->assertOk();
        $html = file_get_contents(public_path('css/app-base.css')).file_get_contents(public_path('css/app-overrides.css'));
        $this->assertMatchesRegularExpression('/\.nav-link\.active:hover\s*\{\s*background:\s*transparent !important/', $html);
    }

    public function test_signed_in_name_and_role_sit_at_the_bottom_of_the_menu(): void
    {
        config(['foundry.require_login' => true]);
        $user = User::factory()->create(['name' => 'Aisha Khan', 'is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('code', 'ADMIN')->first());
        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();
        $aside = substr($html, strpos($html, '<aside'), strpos($html, '</aside>') - strpos($html, '<aside'));
        $this->assertStringContainsString('Aisha Khan', $aside);
        $this->assertStringContainsString($user->roles->first()->name, $aside);
        $this->assertStringContainsString('Sign out', $aside);
        $this->assertGreaterThan(strpos($aside, '</nav>'), strpos($aside, 'sidebar-profile'));
        $topbar = substr($html, strpos($html, '<header'), strpos($html, '</header>') - strpos($html, '<header'));
        $this->assertStringNotContainsString('Sign out', $topbar);
    }

    public function test_sales_orders_in_draft_or_review_are_counted_on_the_item_and_its_group(): void
    {
        $customer = Customer::create(['code' => 'C1', 'name' => 'Acme']);
        foreach (['DRAFT', 'UNDER_REVIEW', 'UNDER_REVIEW', 'CONFIRMED'] as $i => $status) {
            SalesOrder::create(['order_number' => 'SO-'.$i, 'customer_id' => $customer->id, 'order_date' => today(), 'status' => $status]);
        }

        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertSame(1, preg_match('/Sales<\/span><span class="nav-count"[^>]*>3</', $html));
        $this->assertSame(1, preg_match('/Sales orders<\/span><span class="nav-count"[^>]*>3</', $html));
    }

    public function test_items_waiting_for_action_are_counted_on_the_item_and_its_group(): void
    {
        CapaAction::create(['source_type' => 'COMPLAINT', 'title' => 'Blowholes', 'severity' => 'MAJOR', 'status' => 'OPEN']);
        CapaAction::create(['source_type' => 'COMPLAINT', 'title' => 'Old one', 'severity' => 'MINOR', 'status' => 'CLOSED']);
        $supplier = Supplier::create(['name' => 'Acme Pig Iron', 'review_interval_days' => 180, 'approval_status' => 'APPROVED']);
        PurchaseOrder::create(['supplier_id' => $supplier->id, 'order_date' => today(), 'status' => 'DRAFT']);
        PurchaseOrder::create(['supplier_id' => $supplier->id, 'order_date' => today(), 'status' => 'ISSUED', 'po_number' => 'PO-1']);
        $product = Product::create(['code' => 'P1', 'name' => 'Grill plate']);
        $revision = ProductRevision::create(['product_id' => $product->id, 'revision' => '0', 'status' => 'APPROVED']);
        foreach (['LATE' => today()->subDay(), 'ONTIME' => today()->addWeek()] as $number => $due) {
            ProductionOrder::create(['production_order_number' => $number, 'product_id' => $product->id, 'product_revision_id' => $revision->id, 'status' => 'RELEASED', 'planned_qty' => 5, 'required_date' => $due]);
        }

        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        foreach (['Corrective actions', 'Purchase orders', 'Production orders', 'Production'] as $label) {
            $this->assertSame(1, preg_match('/<span>'.$label.'<\/span><span class="nav-count"[^>]*>1</', $html), $label);
        }
        $this->assertSame(1, preg_match('/<span>Quality<\/span><span class="nav-count"[^>]*>(\d+)</', $html, $quality));
        $this->assertGreaterThanOrEqual(1, (int) $quality[1]);
    }

    public function test_the_menu_is_ordered_like_the_design_with_sales_first(): void
    {
        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $nav = substr($html, strpos($html, '<nav class="sidebar-nav"'), strpos($html, '</nav>') - strpos($html, '<nav class="sidebar-nav"'));

        $this->assertSame(1, preg_match('/Sales<\/span>.*Overview.*Enquiries.*Quotations.*Sales orders.*Engineering<\/span>.*Feasibility.*Patterns &amp; tooling.*Production<\/span>.*Planning.*Melt &amp; heats.*Quality<\/span>.*Inspection.*NCR &amp; CAPA.*Audits &amp; documents.*Logistics<\/span>.*Dispatch/s', $nav));
        $this->assertStringNotContainsString('nav-top', $nav);
    }
}
