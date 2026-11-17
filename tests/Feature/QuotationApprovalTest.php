<?php

namespace Tests\Feature;

use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Models\Quotation;
use App\Domains\Enquiry\Services\QuotationService;
use App\Models\ApprovalPolicy;
use App\Models\ApprovalRequest;
use App\Models\Customer;
use App\Services\Approvals\ApprovalGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The margin approval (design 30d): a price under the floor waits for an approver who approves, attaches a condition or sends it back. */
class QuotationApprovalTest extends TestCase
{
    use RefreshDatabase;

    /** A draft priced under the margin floor, with the approval rule on. */
    private function lowMargin(bool $policy = true): Quotation
    {
        if ($policy) {
            ApprovalPolicy::create(['action_key' => 'quotation.issue_low_margin', 'is_active' => true, 'required_approvals' => 1, 'permission' => 'approvals.decide', 'allow_self_approval' => true]);
        }
        $c = Customer::create(['code' => 'C'.uniqid(), 'name' => 'Stellar', 'payment_terms' => 'Net 60']);
        $e = Enquiry::create(['customer_id' => $c->id, 'title' => 'Bearing cap', 'source_type' => 'DRAWING', 'status' => 'FEASIBILITY', 'received_on' => today()->subDays(6)]);
        $e->items()->create(['line_no' => 1, 'description' => 'Bearing cap', 'material_text' => 'EN-GJS-500-7', 'weight_kg' => 6.4, 'annual_qty' => 30000, 'first_batch_qty' => 500, 'family' => 'DUCTILE', 'feasibility_status' => 'OK']);
        $svc = app(QuotationService::class);
        $q = $svc->create($e->fresh('items'));
        $q->lines->first()->update(['margin_pct' => 4]);
        $svc->recalc($q->fresh('lines'));

        return $q->fresh('lines');
    }

    private function ask(Quotation $q): Quotation
    {
        app(QuotationService::class)->issue($q->fresh());

        return $q->fresh();
    }

    public function test_sending_under_the_floor_waits_for_approval_and_shows_in_the_list(): void
    {
        $q = $this->ask($this->lowMargin());

        $this->assertSame('AWAITING_APPROVAL', $q->status);
        $this->assertNotNull($q->approval_asked_at);
        $this->assertSame(1, ApprovalRequest::where('subject_id', $q->id)->where('status', 'PENDING')->count());
        $this->assertSame('APPROVAL', $q->events()->first()->kind);
        $this->get(route('quotations.index', ['tab' => 'approval']))->assertOk()->assertSee($q->code)->assertSeeText('Awaiting approval 1')->assertSee('Review the approval');
        $this->get(route('quotations.show', $q))->assertOk()->assertSee('Waiting for approval')->assertSee(route('quotations.approval', $q));
    }

    public function test_the_approval_page_shows_the_gap_what_changed_and_what_the_part_fetched_before(): void
    {
        $q = $this->ask($this->lowMargin());

        $this->get(route('quotations.approval', $q))->assertOk()
            ->assertSeeText('is asking for')->assertSeeText('The floor is')->assertSeeText('The gap is')->assertSeeText('The quotation')->assertSeeText('What this part has fetched before')
            ->assertSeeText('Approve at')->assertSeeText('Approve with a condition')->assertSeeText('Send back with a note')->assertSee('Margin under');
    }

    public function test_approving_makes_it_ready_to_send_and_sending_then_goes_through(): void
    {
        $q = $this->ask($this->lowMargin());

        $this->post(route('quotations.approval.decide', $q), ['choice' => 'approve'])->assertRedirect()->assertSessionHas('success');

        $q = $q->fresh();
        $this->assertSame('DRAFT', $q->status);
        $this->assertNotNull($q->approved_at);
        $this->assertStringContainsString('Approved at', $q->events()->first()->text);
        $this->assertNull(app(QuotationService::class)->issue($q));
        $this->assertSame('ISSUED', $q->fresh()->status);
    }

    public function test_a_condition_must_be_met_before_it_can_be_sent(): void
    {
        $q = $this->ask($this->lowMargin());

        $this->post(route('quotations.approval.decide', $q), ['choice' => 'condition', 'condition' => ''])->assertSessionHasErrors('approval');
        $this->post(route('quotations.approval.decide', $q), ['choice' => 'condition', 'condition' => 'Tooling paid up front'])->assertRedirect();

        $q = $q->fresh();
        $this->assertSame('Tooling paid up front', $q->approval_condition);
        $this->assertStringContainsString('Tooling paid up front', (string) app(QuotationService::class)->issue($q));
        $this->assertSame('DRAFT', $q->fresh()->status);

        $this->post(route('quotations.condition-met', $q))->assertRedirect();
        $this->assertNull($q->fresh()->approval_condition);
        $this->assertStringContainsString('Condition met', $q->events()->first()->text);
        $this->assertNull(app(QuotationService::class)->issue($q->fresh()));
        $this->assertSame('ISSUED', $q->fresh()->status);
    }

    public function test_sending_it_back_needs_a_note_and_returns_it_to_a_draft(): void
    {
        $q = $this->ask($this->lowMargin());

        $this->post(route('quotations.approval.decide', $q), ['choice' => 'back', 'note' => ''])->assertSessionHasErrors('approval');
        $this->post(route('quotations.approval.decide', $q), ['choice' => 'back', 'note' => 'Try four cavities on one plate first'])->assertRedirect();

        $q = $q->fresh();
        $this->assertSame('DRAFT', $q->status);
        $this->assertNull($q->approval_asked_at);
        $this->assertStringContainsString('Sent back', $q->events()->first()->text);
        $this->assertStringContainsString('four cavities', $q->events()->first()->text);
    }

    public function test_deciding_on_the_approvals_queue_moves_the_quotation_too(): void
    {
        $q = $this->ask($this->lowMargin());
        $request = ApprovalRequest::where('subject_id', $q->id)->firstOrFail();

        app(ApprovalGate::class)->decide($request, 'APPROVE', null, null);

        $this->assertSame('DRAFT', $q->fresh()->status);
        $this->assertNotNull($q->fresh()->approved_at);
    }

    public function test_the_approvals_queue_links_to_the_quotation_review(): void
    {
        $q = $this->ask($this->lowMargin());

        $this->get(route('approvals.index'))->assertOk()->assertSee(route('quotations.approval', $q));
    }

    public function test_with_the_rule_off_a_low_price_is_sent_straight_away(): void
    {
        $q = $this->lowMargin(false);

        $this->assertNull(app(QuotationService::class)->issue($q));
        $this->assertSame('ISSUED', $q->fresh()->status);
    }

    public function test_a_quotation_that_is_not_waiting_has_nothing_to_decide(): void
    {
        $q = $this->lowMargin(false);

        $this->get(route('quotations.approval', $q))->assertOk()->assertSee('nothing is waiting on an approver')->assertDontSee('Approve at');
        $this->post(route('quotations.approval.decide', $q), ['choice' => 'approve'])->assertSessionHasErrors('approval');
        $this->assertSame('DRAFT', $q->fresh()->status);
    }
}
