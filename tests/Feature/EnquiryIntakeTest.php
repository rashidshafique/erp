<?php

namespace Tests\Feature;

use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Services\QuotationService;
use App\Models\Attachment;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The enquiry intake page (design 1b): one page that creates an enquiry and edits it while it is open. */
class EnquiryIntakeTest extends TestCase
{
    use RefreshDatabase;

    private ?Customer $cust = null;

    private function customer(): Customer
    {
        return $this->cust ??= Customer::create(['code' => 'ACME', 'name' => 'Acme Pumps', 'contact_name' => 'Sara Khan']);
    }

    /** @param  array<string, mixed>  $part */
    private function submit(array $d, array $part = [], ?Enquiry $enquiry = null)
    {
        $payload = $d + ['customer_mode' => 'EXISTING', 'customer_id' => $this->customer()->id, 'items' => [$part + ['description' => 'Pump housing']]];

        return $enquiry ? $this->put(route('enquiries.update', $enquiry), $payload) : $this->post(route('enquiries.store'), $payload);
    }

    public function test_the_page_shows_the_1b_sections_and_the_old_wizard_address_still_opens_it(): void
    {
        $this->get(route('enquiries.create'))->assertOk()
            ->assertSee('Customer RFQ ref')->assertSee('Reply by')->assertSee('Kind of request')->assertSee('Volumes')->assertSee('Quality requirements')
            ->assertSee('Similar parts we\'ve made', false)->assertSee('Save draft')->assertSee('Send to feasibility');
        foreach (config('enquiry_scenarios') as $s) {
            $this->get(route('enquiries.create'))->assertSee($s['label']);
        }
        $customer = $this->customer();
        $this->get(route('enquiries.wizard', ['customer' => $customer->id, 'scenario' => 'SAMPLE']))->assertRedirect(route('enquiries.create', ['customer' => $customer->id, 'scenario' => 'SAMPLE']));
        $this->get(route('enquiries.create', ['customer' => $customer->id, 'scenario' => 'SAMPLE']))->assertOk()->assertSeeHtml('value="SAMPLE" checked');
    }

    public function test_a_draft_needs_only_the_customer_and_kind_and_send_checks_everything_the_kind_needs(): void
    {
        $this->submit(['source_type' => 'SAMPLE', 'action' => 'draft'])->assertSessionHasNoErrors();
        $e = Enquiry::firstOrFail();
        $this->assertSame('NEW', $e->status);
        $this->assertSame('Pump housing (Sample)', $e->title);
        $this->assertSame('Sara Khan', $e->contact_name);
        $this->get(route('enquiries.show', $e))->assertOk()->assertSee('Draft')->assertSee('Send to feasibility');

        $item = $e->items->first();
        $this->submit(['source_type' => 'SAMPLE', 'action' => 'send'], ['id' => $item->id], $e)->assertSessionHasErrors('summary');
        $this->assertSame('NEW', $e->fresh()->status);
        $this->submit(['source_type' => 'SAMPLE', 'action' => 'send', 'summary' => 'Two worn gears'], ['id' => $item->id], $e)->assertSessionHasNoErrors();
        $this->assertSame('FEASIBILITY', $e->fresh()->status);
        $this->assertTrue($e->events()->where('text', 'Sent to feasibility.')->exists());
    }

    public function test_send_needs_a_part_and_new_work_needs_a_drawing_number_or_a_file(): void
    {
        Storage::fake('local');
        $this->post(route('enquiries.store'), ['customer_id' => $this->customer()->id, 'source_type' => 'DRAWING', 'action' => 'send'])->assertSessionHasErrors('items');
        $this->submit(['source_type' => 'DRAWING', 'action' => 'send'])->assertSessionHasErrors('items.0.drawing_ref');
        $this->submit(['source_type' => 'DRAWING', 'action' => 'send', 'files' => [UploadedFile::fake()->create('housing.pdf', 20, 'application/pdf')]])->assertSessionHasNoErrors();
        $e = Enquiry::firstOrFail();
        $this->assertSame('FEASIBILITY', $e->status);
        $this->assertSame(1, Attachment::where('attachable_type', 'enquiry')->where('attachable_id', $e->id)->count());
    }

    public function test_a_repeat_needs_one_of_our_parts_and_takes_its_weight(): void
    {
        $p = Product::create(['code' => 'PRD-1', 'name' => 'Housing', 'product_type' => 'CASTING', 'base_uom' => 'piece', 'nominal_weight_kg' => 12.5, 'active' => true]);
        ProductRevision::create(['product_id' => $p->id, 'revision' => 'B', 'status' => 'APPROVED']);
        $this->submit(['source_type' => 'REPEAT', 'action' => 'send'])->assertSessionHasErrors('items.0.product_id');
        $this->submit(['source_type' => 'REPEAT', 'action' => 'send'], ['product_id' => $p->id, 'first_batch_qty' => 100])->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(12.5, (float) Enquiry::firstOrFail()->items->first()->weight_kg, 0.001);
    }

    public function test_the_intake_fields_and_quality_chips_are_saved_and_ppap_needs_a_level(): void
    {
        $part = ['drawing_ref' => 'MAS-44817', 'process' => 'GREEN_SAND', 'supply_condition' => 'MACHINED_PAINTED', 'call_off' => '8,000 / month', 'sop_on' => '2027-04-01',
            'programme_years' => 6, 'annual_qty' => 96000, 'quality' => ['PPAP', 'MPI'], 'quality_other' => 'CSR v4.2', 'machining_cost' => 3.5];
        $this->submit(['source_type' => 'DRAWING', 'action' => 'send'], $part)->assertSessionHasErrors('items.0.ppap_level');
        $this->submit(['source_type' => 'DRAWING', 'action' => 'send', 'customer_reference' => 'MAS-RFQ-88213', 'due_on' => '2026-11-07'], $part + ['ppap_level' => 3])->assertSessionHasNoErrors();

        $item = Enquiry::firstOrFail()->items->first();
        $this->assertSame('GREEN_SAND', $item->process);
        $this->assertSame('2027-04-01', $item->sop_on->toDateString());
        $this->assertSame(96000, $item->annual_qty);
        $this->assertEqualsWithDelta(3.5, $item->machining_cost, 0.001);
        $this->assertSame('PPAP level 3, MPI 100%, CSR v4.2', $item->qualityText());
        $this->assertSame('MAS-RFQ-88213', Enquiry::firstOrFail()->customer_reference);
    }

    public function test_a_new_customer_is_made_on_the_spot_but_not_twice(): void
    {
        $this->post(route('enquiries.store'), ['customer_mode' => 'NEW', 'new_name' => 'Orbis Gears', 'new_contact' => 'Ali', 'source_type' => 'CONCEPT', 'summary' => 'Gear cover', 'action' => 'send', 'items' => [['description' => 'Cover']]])
            ->assertSessionHasNoErrors();
        $customer = Customer::whereHas('party', fn ($q) => $q->where('name', 'Orbis Gears'))->firstOrFail();
        $this->assertTrue($customer->is_prospect);
        $this->assertSame('ORBI', $customer->code);
        $this->post(route('enquiries.store'), ['customer_mode' => 'NEW', 'new_name' => 'orbis gears', 'source_type' => 'CONCEPT', 'action' => 'draft'])->assertSessionHasErrors('new_name');
    }

    public function test_editing_adds_updates_and_removes_parts_but_not_one_on_a_live_quotation(): void
    {
        $this->submit(['source_type' => 'DRAWING', 'action' => 'send'], ['drawing_ref' => 'D-1', 'weight_kg' => 10, 'first_batch_qty' => 50, 'family' => 'GREY'])->assertSessionHasNoErrors();
        $e = Enquiry::firstOrFail();
        $first = $e->items->first();
        $this->get(route('enquiries.edit', $e))->assertOk()->assertSee('Back to the record');

        $this->submit(['source_type' => 'DRAWING', 'action' => 'save'], ['id' => $first->id, 'description' => '', 'drawing_ref' => 'D-1'], $e)->assertSessionHasErrors('items.0.description');
        $this->put(route('enquiries.update', $e), ['customer_id' => $e->customer_id, 'source_type' => 'DRAWING', 'action' => 'save', 'items' => [
            ['id' => $first->id, 'description' => 'Pump housing, machined', 'drawing_ref' => 'D-1', 'weight_kg' => 10, 'first_batch_qty' => 50, 'family' => 'GREY'],
            ['description' => 'Cover', 'drawing_ref' => 'D-2', 'weight_kg' => 2, 'first_batch_qty' => 20, 'family' => 'GREY'],
        ]])->assertSessionHasNoErrors();
        $this->assertSame(['Pump housing, machined', 'Cover'], $e->fresh()->items->pluck('description')->all());
        $this->assertSame([1, 2], $e->fresh()->items->pluck('line_no')->all());

        $e->items()->update(['feasibility_status' => 'OK']);
        app(QuotationService::class)->create($e->fresh());
        $second = $e->fresh()->items->last();
        $this->put(route('enquiries.update', $e), ['customer_id' => $e->customer_id, 'source_type' => 'DRAWING', 'action' => 'save', 'items' => [
            ['id' => $first->id, 'description' => 'Pump housing, machined', 'drawing_ref' => 'D-1'], ['id' => $second->id, 'description' => 'Cover', 'remove' => 1],
        ]])->assertSessionHasErrors('items.1.remove');
        $this->assertSame(2, $e->items()->count());
    }

    public function test_the_customer_ppap_level_is_offered_and_similar_parts_are_shown(): void
    {
        $customer = $this->customer();
        $customer->update(['ppap_requested' => true, 'ppap_level' => 3]);
        $this->get(route('enquiries.create', ['customer' => $customer->id]))->assertOk()->assertSee('PPAP L3')->assertSeeHtml('data-ppap="3"');

        $grade = \App\Models\IronGrade::listed()->first();
        $this->assertNotNull($grade, 'the seeded iron grades are needed');
        $similar = Product::create(['code' => 'PRD-7', 'name' => 'Caliper bracket RH', 'product_type' => 'CASTING', 'base_uom' => 'piece', 'nominal_weight_kg' => 3.5, 'active' => true]);
        $rev = ProductRevision::create(['product_id' => $similar->id, 'revision' => 'A', 'status' => 'APPROVED']);
        \App\Models\ProductSpecification::create(['product_revision_id' => $rev->id, 'specification_code' => 'SPEC-1', 'title' => 'Material', 'material_grade' => $grade->code]);
        $this->submit(['source_type' => 'DRAWING', 'action' => 'draft'], ['material_text' => $grade->code, 'weight_kg' => 3.62])->assertSessionHasNoErrors();
        $this->get(route('enquiries.show', Enquiry::firstOrFail()))->assertOk()->assertSee('Caliper bracket RH');
    }

    public function test_only_sales_can_save_an_enquiry(): void
    {
        config(['foundry.require_login' => true]);
        $mk = function (string $role) {
            $u = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
            $u->roles()->attach(Role::where('code', $role)->value('id'));

            return $u;
        };
        $this->actingAs($mk('VIEWER'))->post(route('enquiries.store'), [])->assertForbidden();
        $this->actingAs($mk('SALES'))->post(route('enquiries.store'), [])->assertSessionHasErrors('source_type');
    }
}
