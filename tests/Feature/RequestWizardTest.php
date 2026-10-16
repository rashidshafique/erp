<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Models\Role;
use App\Models\User;
use App\Services\Quotation\EnquiryGuide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RequestWizardTest extends TestCase
{
    use RefreshDatabase;

    private ?Customer $cust = null;

    private function customer(): Customer
    {
        return $this->cust ??= Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps', 'contact_name' => 'Sara Khan']);
    }

    private function submit(array $d, array $extra = [])
    {
        return $this->post(route('enquiries.wizard.store'), $d + ['customer_mode' => 'EXISTING', 'customer_id' => $this->customer()->id] + $extra);
    }

    public function test_wizard_page_lists_every_kind_of_request(): void
    {
        $r = $this->get(route('enquiries.wizard'))->assertOk();
        foreach (config('enquiry_scenarios') as $s) {
            $r->assertSee($s['label']);
        }
        $this->get(route('enquiries.index'))->assertOk()->assertSee('New request (guided)');
    }

    public function test_repeat_needs_one_of_our_parts_and_takes_its_weight(): void
    {
        $this->submit(['source_type' => 'REPEAT', 'description' => 'More housings'])->assertSessionHasErrors('product_id');
        $p = Product::create(['code' => 'PRD-1', 'name' => 'Housing', 'product_type' => 'CASTING', 'base_uom' => 'piece', 'nominal_weight_kg' => 12.5, 'active' => true]);
        ProductRevision::create(['product_id' => $p->id, 'revision' => 'B', 'status' => 'APPROVED']);
        $this->submit(['source_type' => 'REPEAT', 'description' => 'More housings', 'product_id' => $p->id, 'first_batch_qty' => 100])->assertSessionHasNoErrors();
        $e = Enquiry::firstOrFail();
        $this->assertSame('FEASIBILITY', $e->status);
        $this->assertSame('REPEAT', $e->source_type);
        $item = $e->items->first();
        $this->assertSame($p->id, $item->product_id);
        $this->assertEqualsWithDelta(12.5, (float) $item->weight_kg, 0.001);
        $this->assertSame('Sara Khan', $e->contact_name);
        $steps = collect(EnquiryGuide::steps($e->fresh()));
        $this->assertTrue($steps->firstWhere('label', 'Every part has an approved revision')['done']);
    }

    public function test_each_kind_asks_for_what_it_needs(): void
    {
        $cases = [
            ['CHANGE', ['description' => 'Housing v2'], ['product_id', 'change_note']],
            ['CUSTOMER_TOOL', ['description' => 'Valve'], ['tool_note']],
            ['CUSTOMER_TOOL', ['description' => 'Valve', 'tool_note' => 'Pattern'], ['drawing_ref']],
            ['CONCEPT', ['description' => 'Bracket'], ['concept_note']],
            ['TRANSFER', ['description' => 'Flange'], ['transfer_note']],
            ['TRANSFER', ['description' => 'Flange', 'transfer_note' => 'Moving from X'], ['drawing_ref']],
            ['SAMPLE', ['description' => 'Old gear'], ['sample_note']],
            ['DRAWING', ['description' => 'Cover'], ['drawing_ref']],
        ];
        $c = $this->customer();
        foreach ($cases as [$src, $data, $expect]) {
            $r = $this->submit(['source_type' => $src, 'customer_id' => $c->id] + $data);
            $r->assertSessionHasErrors($expect);
        }
        $this->assertSame(0, Enquiry::count());
    }

    public function test_drawing_request_with_a_file_instead_of_a_number_is_accepted_and_attached(): void
    {
        Storage::fake('local');
        config(['foundry.attachments.disk' => 'local']);
        $this->submit(['source_type' => 'DRAWING', 'description' => 'Cover plate', 'files' => [UploadedFile::fake()->createWithContent('cover.stl', "solid x\nendsolid x\n")], 'file_category' => 'DRAWING'])->assertSessionHasNoErrors();
        $e = Enquiry::firstOrFail();
        $a = Attachment::where('attachable_type', 'enquiry')->where('attachable_id', $e->id)->firstOrFail();
        $this->assertSame('DRAWING', $a->category);
        $this->assertSame('cover.stl', $a->original_name);
    }

    public function test_customer_supplied_tool_is_recorded_as_such(): void
    {
        $this->submit(['source_type' => 'CUSTOMER_TOOL', 'description' => 'Valve body', 'drawing_ref' => 'V-12', 'tool_note' => 'Aluminium pattern and 2 core boxes, worn'])->assertSessionHasNoErrors();
        $e = Enquiry::firstOrFail();
        $this->assertStringContainsString('Aluminium pattern', $e->summary);
        $this->assertStringContainsString('Customer-owned tooling', $e->items->first()->notes);
        $this->assertEquals(0, $e->items->first()->tooling_cost);   // we do not price tooling we do not make
    }

    public function test_new_customer_is_created_with_a_unique_code_and_duplicates_are_refused(): void
    {
        $d = ['customer_mode' => 'NEW', 'new_name' => 'Beta Valves (Pvt) Ltd', 'new_terms' => '45 days', 'source_type' => 'CONCEPT', 'description' => 'Manifold', 'concept_note' => 'Hydraulic, 200 bar'];
        $this->submit($d, ['customer_id' => null])->assertSessionHasNoErrors();
        $c = Customer::where('name', 'Beta Valves (Pvt) Ltd')->firstOrFail();
        $this->assertSame('BETA', $c->code);
        $this->assertSame('45 days', $c->payment_terms);
        $this->assertSame($c->id, Enquiry::firstOrFail()->customer_id);

        $this->submit(['new_name' => 'beta valves (pvt) ltd'] + $d, ['customer_id' => null])->assertSessionHasErrors('new_name');
        $this->assertSame(1, Customer::where('code', 'like', 'BETA%')->count());
        $this->submit(['new_name' => 'Beta Bearings'] + $d, ['customer_id' => null])->assertSessionHasNoErrors();
        $this->assertSame('BETA01', Customer::where('name', 'Beta Bearings')->value('code'));
    }

    public function test_change_request_leads_into_a_new_revision_when_won(): void
    {
        $p = Product::create(['code' => 'PRD-2', 'name' => 'Cover', 'product_type' => 'CASTING', 'base_uom' => 'piece', 'nominal_weight_kg' => 5, 'active' => true]);
        ProductRevision::create(['product_id' => $p->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $this->submit(['source_type' => 'CHANGE', 'description' => 'Cover, thicker rib', 'product_id' => $p->id, 'change_note' => 'Rib 6 to 9 mm', 'drawing_ref' => 'C-9', 'drawing_rev' => 'B', 'first_batch_qty' => 30])->assertSessionHasNoErrors();
        $e = Enquiry::firstOrFail();
        $e->items->first()->update(['feasibility_status' => 'OK']);
        $svc = app(\App\Services\Quotation\QuotationService::class);
        $q = $svc->create($e->fresh());
        $this->assertNull($svc->issue($q->fresh()));
        $order = $svc->accept($q->fresh());
        $this->assertSame('B', ProductRevision::find($order->lines()->first()->product_revision_id)->revision);
    }

    public function test_enquiry_page_shows_the_checklist_and_wizard_respects_permissions(): void
    {
        $this->submit(['source_type' => 'SAMPLE', 'description' => 'Old gear', 'sample_note' => 'Two worn gears'])->assertSessionHasNoErrors();
        $e = Enquiry::firstOrFail();
        $this->get(route('enquiries.show', $e))->assertOk()->assertSee('What happens next')->assertSee('Photograph, weigh and measure');

        config(['foundry.require_login' => true]);
        $mk = function (string $role) {
            $u = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
            $u->roles()->attach(Role::where('code', $role)->value('id'));

            return $u;
        };
        $this->actingAs($mk('VIEWER'))->post(route('enquiries.wizard.store'), [])->assertForbidden();
        $this->actingAs($mk('SALES'))->post(route('enquiries.wizard.store'), [])->assertSessionHasErrors('source_type');
    }
}
