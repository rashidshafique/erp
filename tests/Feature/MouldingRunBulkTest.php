<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\MouldingRun;
use App\Models\MouldingStation;
use App\Models\MouldingTeam;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductRevision;
use App\Models\QualityDefect;
use App\Services\Production\BulkRunPaste;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MouldingRunBulkTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{pattern: Pattern, revision: PatternRevision, order: ProductionOrder, team: MouldingTeam, station: MouldingStation} */
    private function world(): array
    {
        $product = Product::create(['code' => 'FLG-1', 'name' => 'Flange']);
        $productRevision = ProductRevision::create(['product_id' => $product->id, 'revision' => 'A', 'status' => 'APPROVED']);
        $pattern = Pattern::create(['code' => 'MP-7', 'name' => 'Flange plate', 'pattern_type' => 'MATCHPLATE']);
        $revision = PatternRevision::create(['pattern_id' => $pattern->id, 'status' => 'APPROVED', 'cavities_per_mould' => 2]);
        $revision->cavityMap()->create(['cavity_number' => 1, 'product_revision_id' => $productRevision->id]);
        $revision->cavityMap()->create(['cavity_number' => 2, 'product_revision_id' => $productRevision->id]);
        $order = ProductionOrder::create(['production_order_number' => 'PO-S1', 'product_id' => $product->id, 'product_revision_id' => $productRevision->id, 'order_type' => 'STOCK', 'status' => 'RELEASED', 'planned_qty' => 1000, 'uom' => 'piece']);
        $team = MouldingTeam::create(['code' => 'T1', 'name' => 'Team Alpha', 'lead_name' => 'Rashid', 'member_count' => 5, 'status' => 'ACTIVE']);
        $station = MouldingStation::create(['code' => 'MP-1', 'name' => 'Match plate machine 1', 'station_type' => 'MATCHPLATE_MACHINE', 'status' => 'ACTIVE', 'default_moulding_team_id' => $team->id, 'default_sand_system' => 'Sheera']);

        return compact('pattern', 'revision', 'order', 'team', 'station');
    }

    /** @return array<string, mixed> */
    private function row(array $w, array $over = []): array
    {
        return $over + [
            'run_date' => '2026-10-17', 'shift' => 'Morning', 'moulding_station_id' => $w['station']->id,
            'pattern_id' => $w['pattern']->id, 'pattern_revision_id' => $w['revision']->id, 'cavity_numbers' => '1, 2',
            'moulds_made_qty' => 40, 'damaged_mould_qty' => 2,
        ];
    }

    public function test_many_rows_are_saved_together_and_blank_rows_ignored(): void
    {
        $w = $this->world();
        $rows = [];
        foreach (['2026-10-13', '2026-10-14', '2026-10-15', '2026-10-16', '2026-10-17'] as $i => $date) {
            $rows["n{$i}"] = $this->row($w, ['run_date' => $date, 'moulds_made_qty' => 40 + $i, 'notes' => "Day {$i}"]);
        }
        $rows['blank'] = ['run_date' => '2026-10-17', 'shift' => 'Morning'];

        $response = $this->post(route('moulding-runs.bulk.save'), ['rows' => $rows, 'action' => 'save']);

        $response->assertRedirect(route('moulding-runs.bulk', ['date' => '2026-10-13', 'shift' => 'Morning']))->assertSessionHas('success', 'Saved: 5 new runs.');
        $this->assertSame(5, MouldingRun::count());
        $run = MouldingRun::where('notes', 'Day 4')->sole();
        $this->assertSame([44, 2, 42, [1, 2], $w['order']->id, 'COMPLETED', $w['team']->id, 'Team Alpha', 'Sheera'], [$run->moulds_made_qty, $run->damaged_mould_qty, $run->accepted_mould_qty, $run->cavity_numbers, $run->production_order_id, $run->status, $run->moulding_team_id, $run->team_name, $run->sand_system]);
    }

    public function test_create_page_maps_each_product_to_its_active_pattern_revisions(): void
    {
        $w = $this->world();

        $response = $this->get(route('moulding-runs.create'));

        $response->assertOk()->assertViewHas('activePatternRevisionIdsByProduct', [
            $w['order']->product_id => [$w['revision']->id],
        ]);

        $w['pattern']->update(['status' => 'INACTIVE']);

        $this->get(route('moulding-runs.create'))->assertViewHas('activePatternRevisionIdsByProduct', []);
    }

    public function test_header_defaults_fill_blank_cells(): void
    {
        $w = $this->world();

        $this->post(route('moulding-runs.bulk.save'), [
            'header' => ['run_date' => '2026-10-17', 'shift' => 'Night', 'moulding_station_id' => $w['station']->id],
            'rows' => ['a' => $this->row($w, ['run_date' => '', 'shift' => '', 'moulding_station_id' => ''])],
        ])->assertSessionHasNoErrors();

        $run = MouldingRun::sole();
        $this->assertSame(['2026-10-17', 'Night', $w['station']->id, $w['team']->id], [$run->run_date->toDateString(), $run->shift, $run->moulding_station_id, $run->moulding_team_id]);
    }

    public function test_one_bad_row_saves_nothing_and_keeps_every_entry(): void
    {
        $w = $this->world();

        $response = $this->post(route('moulding-runs.bulk.save'), ['rows' => [
            'a' => $this->row($w, ['notes' => 'Good row']),
            'b' => $this->row($w, ['shift' => 'Evening', 'damaged_mould_qty' => 50]),
            'c' => $this->row($w, ['shift' => 'Night']),
        ]]);

        $response->assertRedirect(route('moulding-runs.bulk'))
            ->assertSessionHasErrors(['rows.b.damaged_mould_qty' => 'Row 2: Rejected moulds (50) cannot be more than moulds made (40).'])
            ->assertSessionHasInput('rows.a.notes', 'Good row');
        $this->assertSame(0, MouldingRun::count());
    }

    public function test_a_failed_save_shows_the_row_and_its_message_in_the_grid(): void
    {
        $w = $this->world();
        $this->post(route('moulding-runs.bulk.save'), ['rows' => ['b' => $this->row($w, ['damaged_mould_qty' => 50, 'notes' => 'Kept remark'])]]);

        $page = $this->get(route('moulding-runs.bulk'));

        $page->assertOk()->assertSee('data-bulk-row="b" class="is-editing has-error"', false)
            ->assertSee('Row 1: Rejected moulds (50) cannot be more than moulds made (40).')->assertSee('value="Kept remark"', false);
    }

    public function test_a_duplicate_run_needs_confirming(): void
    {
        $w = $this->world();
        $this->post(route('moulding-runs.bulk.save'), ['rows' => ['a' => $this->row($w)]])->assertSessionHasNoErrors();
        $saved = MouldingRun::sole()->run_number;

        $this->post(route('moulding-runs.bulk.save'), ['rows' => ['a' => $this->row($w)]])
            ->assertSessionHasErrors(['rows.a.confirm_duplicate' => "Row 1: Run {$saved} already has this date, shift, station, pattern and revision. Tick “Save anyway” if this really is a second run."]);
        $this->post(route('moulding-runs.bulk.save'), ['rows' => ['a' => $this->row($w, ['shift' => 'Night']), 'b' => $this->row($w, ['shift' => 'Night'])]])
            ->assertSessionHasErrors(['rows.b.confirm_duplicate' => 'Row 2: Same date, shift, station, pattern and revision as row 1. Tick “Save anyway” if this really is a second run.']);
        $this->assertSame(1, MouldingRun::count());

        $this->post(route('moulding-runs.bulk.save'), ['rows' => ['a' => $this->row($w, ['confirm_duplicate' => '1'])]])->assertSessionHasNoErrors();
        $this->assertSame(2, MouldingRun::count());
    }

    public function test_cavities_must_be_on_the_revision_layout(): void
    {
        $w = $this->world();

        $this->post(route('moulding-runs.bulk.save'), ['rows' => ['a' => $this->row($w, ['cavity_numbers' => '1, 3'])]])
            ->assertSessionHasErrors(['rows.a.cavity_numbers' => 'Row 1: Cavity 3 is not on the MP-7-R0 layout (mapped: 1, 2).']);
        $this->post(route('moulding-runs.bulk.save'), ['rows' => ['a' => $this->row($w, ['cavity_numbers' => 'one'])]])
            ->assertSessionHasErrors(['rows.a.cavity_numbers' => 'Row 1: “one” is not a cavity number. Use numbers such as 1, 2 or 1-4.']);
        $this->assertSame(0, MouldingRun::count());
    }

    public function test_the_revision_must_belong_to_the_pattern(): void
    {
        $w = $this->world();
        $other = PatternRevision::create(['pattern_id' => Pattern::create(['code' => 'MP-9', 'name' => 'Other'])->id, 'status' => 'APPROVED']);

        $this->post(route('moulding-runs.bulk.save'), ['rows' => ['a' => $this->row($w, ['pattern_revision_id' => $other->id])]])
            ->assertSessionHasErrors(['rows.a.pattern_revision_id' => 'Row 1: MP-9-R0 is not a revision of the chosen pattern.']);
        $this->assertSame(0, MouldingRun::count());
    }

    public function test_a_superseded_revision_is_saved_with_a_warning(): void
    {
        $w = $this->world();
        $w['pattern']->addRevision(['change_description' => 'New gating'], copyLayout: true);

        $response = $this->post(route('moulding-runs.bulk.save'), ['rows' => ['a' => $this->row($w)]]);

        $response->assertSessionHasNoErrors()->assertSessionHas('warning', 'Row 1: MP-7-R0 is superseded; the run was saved against it as entered.');
        $this->assertSame($w['revision']->id, MouldingRun::sole()->pattern_revision_id);
    }

    public function test_a_saved_shift_opens_read_only_and_corrections_are_audited(): void
    {
        $w = $this->world();
        $defect = QualityDefect::where('code', 'SAND-INCL')->firstOrFail();
        $this->post(route('moulding-runs.bulk.save'), ['rows' => ['a' => $this->row($w)]]);
        $run = MouldingRun::sole();

        $this->get(route('moulding-runs.bulk', ['date' => '2026-10-17', 'shift' => 'Morning']))->assertOk()
            ->assertSee('data-bulk-row="'.$run->id.'" class=" "', false)->assertSee('aria-label="Edit '.$run->run_number.'"', false)->assertSee('data-row-edit', false);

        $response = $this->post(route('moulding-runs.bulk.save'), ['opened_date' => '2026-10-17', 'opened_shift' => 'Morning', 'rows' => [
            $run->id => $this->row($w, ['id' => $run->id, 'damaged_mould_qty' => 5, 'quality_defect_id' => $defect->id]),
        ]]);

        $response->assertSessionHasNoErrors()->assertSessionHas('success', 'Saved: 1 run corrected.');
        $this->assertSame(1, MouldingRun::count());
        $this->assertSame([5, 35, $defect->id], [$run->fresh()->damaged_mould_qty, $run->fresh()->accepted_mould_qty, $run->fresh()->quality_defect_id]);
        $this->assertTrue(AuditLog::where('entity_type', 'MouldingRun')->where('entity_id', $run->id)->where('action', 'UPDATED')->exists());
    }

    public function test_pasted_spreadsheet_rows_are_read_into_grid_rows(): void
    {
        $w = $this->world();
        $text = "Date\tShift\tStation\tPattern\tRevision\tCavities\tOrder\tMade\tRejected\tDefect\tRemarks\n"
            ."17/10/2026\tMorning\tmp-1\tmp-7\t\t1-2\tPO-S1\t1,200\t3\tSAND-INCL\tGood shift\n"
            ."2026-10-18\tNight\tXX-9\tMP-7\tR7\n";

        $parsed = app(BulkRunPaste::class)->parse($text);

        $rows = array_values($parsed['rows']);
        $this->assertCount(2, $rows);
        $this->assertSame(['2026-10-17', 'Morning', $w['station']->id, $w['pattern']->id, $w['revision']->id, '1-2', $w['order']->id, 1200, 3, 'Good shift'],
            [$rows[0]['run_date'], $rows[0]['shift'], $rows[0]['moulding_station_id'], $rows[0]['pattern_id'], $rows[0]['pattern_revision_id'], $rows[0]['cavity_numbers'], $rows[0]['production_order_id'], $rows[0]['moulds_made_qty'], $rows[0]['damaged_mould_qty'], $rows[0]['notes']]);
        $this->assertSame(['Pasted line 3: No station “XX-9”.', 'Pasted line 3: Pattern MP-7 has no revision “R7”.'], array_values($parsed['errors']));
    }

    public function test_pasting_fills_the_grid_without_saving(): void
    {
        $this->world();

        $response = $this->post(route('moulding-runs.bulk.save'), ['action' => 'paste', 'paste' => "17/10/2026\tMorning\tMP-1\tMP-7\t\t1\t\t30\t1"]);

        $response->assertRedirect(route('moulding-runs.bulk'))->assertSessionHas('success', '1 pasted row added to the grid. Check them, then save.');
        $this->assertSame(0, MouldingRun::count());
        $this->get(route('moulding-runs.bulk'))->assertOk()->assertSee('value="30"', false);
    }

    public function test_backfill_gives_a_run_without_a_revision_the_one_on_its_work_list_item(): void
    {
        $w = $this->world();
        $kept = PatternRevision::create(['pattern_id' => Pattern::create(['code' => 'MP-8', 'name' => 'Other plate'])->id, 'status' => 'APPROVED']);
        $listId = (string) Str::uuid();
        $itemId = (string) Str::uuid();
        DB::table('moulding_work_lists')->insert(['id' => $listId, 'list_number' => 'WL-T1', 'list_date' => '2026-10-17', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('moulding_work_list_items')->insert(['id' => $itemId, 'moulding_work_list_id' => $listId, 'moulding_station_id' => $w['station']->id, 'pattern_revision_id' => $w['revision']->id, 'planned_moulds' => 10, 'created_at' => now(), 'updated_at' => now()]);
        $blank = MouldingRun::create(['run_number' => 'MR-T1', 'production_order_id' => $w['order']->id, 'run_date' => '2026-10-17', 'moulding_work_list_item_id' => $itemId, 'status' => 'COMPLETED']);
        $own = MouldingRun::create(['run_number' => 'MR-T2', 'production_order_id' => $w['order']->id, 'run_date' => '2026-10-17', 'moulding_work_list_item_id' => $itemId, 'pattern_revision_id' => $kept->id, 'status' => 'COMPLETED']);

        (require database_path('migrations/2026_10_21_110000_add_revision_costs_cavity_copies_and_run_details.php'))->up();

        $this->assertSame($w['revision']->id, $blank->fresh()->pattern_revision_id);
        $this->assertSame($kept->id, $own->fresh()->pattern_revision_id);
    }

    public function test_team_and_sand_follow_the_station_and_are_corrected_on_the_run_page(): void
    {
        $w = $this->world();
        $teamB = MouldingTeam::create(['code' => 'T2', 'name' => 'Team Beta', 'lead_name' => 'Imtiaz', 'member_count' => 4, 'status' => 'ACTIVE']);
        $floor = MouldingStation::create(['code' => 'FL-1', 'name' => 'Floor 1', 'station_type' => 'FLOOR', 'status' => 'ACTIVE', 'default_moulding_team_id' => $teamB->id, 'default_sand_system' => 'Greensand']);
        $this->post(route('moulding-runs.bulk.save'), ['rows' => ['a' => $this->row($w, ['moulding_team_id' => $teamB->id, 'sand_system' => 'Alphaset'])]])->assertSessionHasNoErrors();
        $run = MouldingRun::sole();
        $this->assertSame([$w['team']->id, 'Sheera'], [$run->moulding_team_id, $run->sand_system], 'team and sand typed into the grid are ignored');

        $this->put(route('moulding-runs.crew', $run), ['moulding_team_id' => $teamB->id, 'sand_system' => 'Alphaset'])->assertSessionHasNoErrors();
        $this->post(route('moulding-runs.bulk.save'), ['rows' => [$run->id => $this->row($w, ['id' => $run->id, 'moulds_made_qty' => 45])]])->assertSessionHasNoErrors();
        $this->assertSame([$teamB->id, 'Team Beta', 'Alphaset', 45], [$run->fresh()->moulding_team_id, $run->fresh()->team_name, $run->fresh()->sand_system, $run->fresh()->moulds_made_qty]);

        $this->post(route('moulding-runs.bulk.save'), ['rows' => [$run->id => $this->row($w, ['id' => $run->id, 'moulding_station_id' => $floor->id, 'moulds_made_qty' => 45])]])->assertSessionHasNoErrors();
        $this->assertSame([$teamB->id, 'Greensand'], [$run->fresh()->moulding_team_id, $run->fresh()->sand_system], 'a new station brings its own team and sand');
    }

    public function test_the_grid_has_no_team_or_sand_inputs_and_a_searchable_pattern_with_its_name_and_photo(): void
    {
        Storage::fake(config('foundry.attachments.disk'));
        $w = $this->world();
        $photo = Attachment::create(['attachable_type' => 'pattern', 'attachable_id' => $w['pattern']->id, 'category' => 'PHOTO', 'original_name' => 'plate.png', 'disk' => config('foundry.attachments.disk'),
            'path' => 'attachments/plate.png', 'mime_type' => 'image/png', 'size_bytes' => 10, 'sha256' => str_repeat('a', 64)]);

        $this->post(route('moulding-runs.bulk.save'), ['rows' => ['a' => $this->row($w)]])->assertSessionHasNoErrors();

        $page = $this->get(route('moulding-runs.bulk', ['date' => '2026-10-17', 'shift' => 'Morning']));

        $page->assertOk()
            ->assertSee('data-search-select="photos"', false)
            ->assertSee('data-img="'.route('attachments.download', $photo).'"', false)
            ->assertSee('data-sub="MP-7"', false)->assertSee('>Flange plate</option>', false)
            ->assertSee('data-revision disabled title="Choose the pattern first"', false)
            ->assertSee('id="bulk-add" title="Add a row (Alt+N)"', false)->assertSee('id="bulk-save" title="Save all rows (Ctrl+S)"', false)
            ->assertSee('T1 · Sheera')
            ->assertDontSee('[moulding_team_id]', false)->assertDontSee('[sand_system]', false);
    }

    public function test_a_blank_revision_means_the_current_one(): void
    {
        $w = $this->world();
        $current = $w['pattern']->addRevision(['change_description' => 'New gating'], copyLayout: true);

        $this->post(route('moulding-runs.bulk.save'), ['rows' => ['a' => $this->row($w, ['pattern_revision_id' => ''])]])->assertSessionHasNoErrors()->assertSessionMissing('warning');

        $this->assertSame($current->id, MouldingRun::sole()->pattern_revision_id);
    }
}
