<?php

namespace Tests\Feature;

use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Models\EnquiryItem;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeasibilityReviewTest extends TestCase
{
    use RefreshDatabase;

    private function item(string $status = 'NEW'): EnquiryItem
    {
        $customer = Customer::create(['code' => 'C'.uniqid(), 'name' => 'Acme Pumps', 'payment_terms' => '30 days']);
        $enquiry = Enquiry::create(['customer_id' => $customer->id, 'title' => 'Pump housing', 'source_type' => 'DRAWING', 'status' => $status, 'received_on' => today()]);

        return $enquiry->items()->create(['line_no' => 1, 'description' => 'Housing', 'feasibility_status' => 'PENDING']);
    }

    public function test_the_queue_lists_parts_waiting_and_the_review_screen_opens(): void
    {
        $item = $this->item();

        $this->get(route('feasibility.index'))->assertOk()->assertSee('Housing');
        $this->get(route('feasibility.show', $item))->assertOk()->assertSee('Methods');
    }

    public function test_the_menu_links_to_feasibility(): void
    {
        $this->get(route('dashboard'))->assertOk()->assertSee(route('feasibility.index'), false);
    }

    public function test_the_part_is_decided_only_once_every_department_has_signed_and_the_worst_verdict_wins(): void
    {
        $item = $this->item();
        $departments = array_keys(config('feasibility.departments'));
        $last = array_pop($departments);

        foreach ($departments as $d) {
            $this->post(route('feasibility.sign', [$item, $d]), ['verdict' => 'OK'])->assertSessionHasNoErrors();
        }
        $this->assertSame('PENDING', $item->fresh()->feasibility_status);

        $this->post(route('feasibility.sign', [$item, $last]), ['verdict' => 'CONDITIONAL', 'notes' => 'Needs a new fixture']);
        $item = $item->fresh();
        $this->assertSame('CONDITIONAL', $item->feasibility_status);
        $this->assertStringContainsString('Needs a new fixture', $item->feasibility_notes);
        $this->assertSame('FEASIBILITY', $item->enquiry->status);
    }

    public function test_a_verdict_other_than_feasible_needs_its_reason_and_closed_enquiries_cannot_be_signed(): void
    {
        $item = $this->item();
        $this->post(route('feasibility.sign', [$item, 'METHODS']), ['verdict' => 'NOT_FEASIBLE'])->assertSessionHas('error');
        $this->assertSame(0, $item->reviews()->count());

        $item->enquiry->update(['status' => 'LOST']);
        $this->post(route('feasibility.sign', [$item, 'METHODS']), ['verdict' => 'OK'])->assertNotFound();
        $this->post(route('feasibility.sign', [$item, 'NOPE']), ['verdict' => 'OK'])->assertNotFound();
    }
}
