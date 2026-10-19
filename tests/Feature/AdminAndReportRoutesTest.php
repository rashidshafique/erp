<?php

namespace Tests\Feature;

use App\Domains\Documents\Models\ControlledDocument;
use App\Models\AppSetting;
use App\Models\ApprovalRequest;
use App\Models\CastingAnalysis;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminAndReportRoutesTest extends TestCase
{
    use RefreshDatabase;

    private function approvalRequest(string $status = 'PENDING'): ApprovalRequest
    {
        return ApprovalRequest::create([
            'action_key' => 'lot.release_hold', 'subject_type' => 'CastingLot', 'subject_id' => (string) \Illuminate\Support\Str::uuid(), 'subject_label' => 'LOT-1',
            'status' => $status, 'required_approvals' => 1, 'allow_self_approval' => false, 'permission' => 'approvals.decide',
        ]);
    }

    public function test_an_open_approval_request_can_be_cancelled_but_a_used_one_cannot(): void
    {
        $open = $this->approvalRequest();
        $used = $this->approvalRequest('USED');

        $this->post(route('approvals.cancel', $open))->assertSessionHas('success');
        $this->assertSame('CANCELLED', $open->fresh()->status);
        $this->post(route('approvals.cancel', $used))->assertSessionHasErrors('approval');
        $this->assertSame('USED', $used->fresh()->status);
    }

    public function test_only_the_requester_or_an_administrator_can_cancel_a_request(): void
    {
        $requester = User::factory()->create();
        $stranger = User::factory()->create();
        $request = $this->approvalRequest();
        $request->update(['requested_by_id' => $requester->id]);

        $this->actingAs($stranger)->post(route('approvals.cancel', $request))->assertSessionHasErrors('approval');
        $this->assertSame('PENDING', $request->fresh()->status);

        $this->actingAs($requester)->post(route('approvals.cancel', $request))->assertSessionHas('success');
        $this->assertSame('CANCELLED', $request->fresh()->status);
    }

    public function test_a_controlled_document_is_edited_and_gets_its_first_review_date(): void
    {
        $document = ControlledDocument::create(['document_number' => 'QP-09', 'title' => 'Sand testing', 'document_type' => 'PROCEDURE', 'status' => 'ACTIVE']);

        $this->put(route('documents.update', $document), ['title' => 'Sand testing procedure', 'document_type' => 'PROCEDURE', 'review_interval_months' => 12])->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertSame('Sand testing procedure', $document->title);
        $this->assertSame(today()->addYear()->toDateString(), $document->next_review_on->toDateString());
        $this->put(route('documents.update', $document), ['title' => 'x', 'document_type' => 'SCROLL'])->assertSessionHasErrors('document_type');
        $this->put(route('documents.update', $document), ['title' => '', 'document_type' => 'PROCEDURE'])->assertSessionHasErrors('title');
    }

    public function test_the_automatic_stock_posting_setting_is_saved(): void
    {
        $this->put(route('settings.update'), [])->assertSessionHasErrors('auto_post_internal_returns_to_stock');
        $this->put(route('settings.update'), ['auto_post_internal_returns_to_stock' => 1])->assertRedirect(route('settings.edit'));

        $this->assertSame('true', AppSetting::getValue('auto_post_internal_returns_to_stock', 'false'));
    }

    public function test_thermal_calibration_with_no_heats_explains_why_it_cannot_run(): void
    {
        $this->post(route('thermal.calibrate'))->assertSessionHasErrors('calibrate');
    }

    public function test_an_analysis_is_deleted_with_its_file_and_a_rerun_checks_its_options(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('analyses/part.stl', 'solid');
        $analysis = CastingAnalysis::create([
            'name' => 'Part', 'status' => 'COMPLETE', 'source_type' => 'STL', 'original_name' => 'part.stl', 'file_disk' => 'local', 'file_path' => 'analyses/part.stl',
            'file_sha256' => 'x', 'file_size' => 5, 'units' => 'mm', 'quality' => 'DRAFT', 'family' => 'GREY', 'score' => 70, 'score_letter' => 'C',
        ]);

        $this->post(route('casting-analyses.rerun', $analysis), ['units' => 'furlongs', 'quality' => 'DRAFT', 'family' => 'GREY', 'feeder' => 'AUTO'])->assertSessionHasErrors('units');
        $this->post(route('casting-analyses.rerun', $analysis), ['units' => 'mm', 'quality' => 'DRAFT', 'family' => 'GREY', 'feeder' => 'MAGIC'])->assertSessionHasErrors('feeder');

        $this->delete(route('casting-analyses.destroy', $analysis))->assertRedirect(route('casting-analyses.index'));
        $this->assertFalse(CastingAnalysis::whereKey($analysis->id)->exists());
        Storage::disk('local')->assertMissing('analyses/part.stl');
    }

    public function test_the_stock_pages_open_and_reject_a_bad_date_range(): void
    {
        $this->get(route('stock-movements.index'))->assertOk();
        $this->get(route('stock-movements.index', ['date_from' => '2026-10-10', 'date_to' => '2026-10-01']))->assertSessionHasErrors('date_to');
        $this->get(route('stock-report.index'))->assertOk();
    }

    public function test_listing_and_form_pages_open(): void
    {
        $user = User::factory()->create();
        $customer = Customer::create(['code' => 'C1', 'name' => 'Acme Pumps']);

        foreach ([
            route('roles.index'), route('users.create'), route('users.edit', $user), route('moulding-runs.index'), route('capa.create'),
            route('requirements.index'), route('requirements.create'), route('customer-requirements.edit', $customer),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_a_packing_record_and_a_requirement_profile_open(): void
    {
        $packing = \App\Domains\Dispatch\Models\PackingRecord::create(['packing_number' => 'PK-1', 'status' => 'DRAFT']);
        $profile = \App\Domains\Compliance\Models\RequirementProfile::first() ?? \App\Domains\Compliance\Models\RequirementProfile::create(['code' => 'TEST', 'name' => 'Test profile']);

        $this->get(route('packing.show', $packing))->assertOk()->assertSee('PK-1');
        $this->get(route('requirements.edit', $profile))->assertOk();
    }

    public function test_logging_out_returns_to_the_login_page(): void
    {
        $this->actingAs(User::factory()->create())->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
