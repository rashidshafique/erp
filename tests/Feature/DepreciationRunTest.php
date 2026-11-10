<?php

namespace Tests\Feature;

use App\Domains\Assets\Models\DepreciationRun;
use App\Domains\Assets\Models\FixedAsset;
use App\Domains\Assets\Services\Depreciation;
use App\Domains\Ledger\Models\Journal;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Ledger\Services\LedgerSettings;
use App\Models\AppSetting;
use App\Models\MouldingRun;
use App\Models\Pattern;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsWorkLists;
use Tests\TestCase;

/** The monthly depreciation run (design 28f, 28k): time-based and usage-based charges, one journal, and reversing it. */
class DepreciationRunTest extends TestCase
{
    use BuildsWorkLists;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-12-02');
        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::where('code', 'ADMIN')->value('id'));
        $this->actingAs($admin);
    }

    /** @return array<string, float> account code => debits less credits, for accounts that moved */
    private function balances(): array
    {
        return LedgerAccount::tree()->where('is_group', false)->filter(fn ($a) => $a->net != 0.0)->mapWithKeys(fn ($a) => [$a->code => $a->net])->sortKeys()->all();
    }

    /** A pattern asset with moulding runs: 60 and 64 moulds in November, a cancelled run and an October run that do not count. */
    private function patternWithRuns(): array
    {
        $w = $this->world();
        $pattern = Pattern::find($w['pr']->pattern_id);
        $asset = FixedAsset::factory()->pattern($pattern, 5000)->create(['cost' => 1240000, 'in_use_on' => '2026-10-01']);
        $run = fn (string $no, string $date, int $moulds, string $status = 'COMPLETED') => MouldingRun::create(['run_number' => $no, 'production_order_id' => $w['order']->id,
            'pattern_revision_id' => $w['pr']->id, 'run_date' => $date, 'status' => $status, 'moulds_made_qty' => $moulds, 'accepted_mould_qty' => $moulds]);

        return ['asset' => $asset, 'runs' => [$run('MR-90', '2026-11-02', 60), $run('MR-97', '2026-11-20', 64), $run('MR-98', '2026-11-21', 10, 'CANCELLED'), $run('MR-80', '2026-10-15', 30)]];
    }

    public function test_straight_line_reducing_balance_and_usage_charges_post_as_one_journal_by_class(): void
    {
        FixedAsset::factory()->create();                                                       // 1,200,000 over 10 years: 10,000 a month
        FixedAsset::factory()->reducingBalance(20)->create(['cost' => 600000, 'in_use_on' => '2026-11-01']);  // 20% a year: 10,000
        $this->patternWithRuns();                                                              // 124 fills × 248

        $this->get(route('assets.depreciation', ['period' => '2026-11']))->assertOk()->assertSee('30,752')->assertSee('MR-90');
        $this->post(route('assets.depreciation.post'), ['period' => '2026-11'])->assertSessionHasNoErrors();

        $this->assertSame(['21-02-02' => -10000.0, '21-02-05' => -10000.0, '21-03-02' => -30752.0, '51-06-03' => 30752.0, '51-06-04' => 10000.0, '61-02-04' => 10000.0], $this->balances());
        $run = DepreciationRun::sole();
        $this->assertSame(['2026-11-30', 50752.0], [$run->journal->journal_date->toDateString(), $run->total]);
        $this->assertSame(1, Journal::where('source_type', 'depreciation_run')->count());
        $this->assertSame([124, 248.0], [$run->lines->firstWhere('charge', 30752)->fills, $run->lines->firstWhere('charge', 30752)->rate]);
        $this->assertSame(1209248.0, FixedAsset::where('kind', 'PATTERN')->sole()->bookValue());

        $this->post(route('assets.depreciation.post'), ['period' => '2026-11'])->assertSessionHasErrors('run');
    }

    public function test_a_part_month_from_the_in_use_date_and_no_charge_below_the_residual_value(): void
    {
        $half = FixedAsset::factory()->create(['in_use_on' => '2026-11-16']);                  // 15 of 30 days
        $nearlyDone = FixedAsset::factory()->create(['residual_value' => 100000, 'opening_depreciation' => 1097000]);  // 3,000 left above residual
        $sold = FixedAsset::factory()->inProgress()->create();

        $lines = app(Depreciation::class)->forMonth(Carbon::parse('2026-11-01'))->keyBy(fn ($l) => $l['asset']->id);

        $this->assertSame(5000.0, $lines[$half->id]['charge']);
        $this->assertSame([3000.0, true], [$lines[$nearlyDone->id]['charge'], $lines[$nearlyDone->id]['capped']]);
        $this->assertArrayNotHasKey($sold->id, $lines->all());
    }

    public function test_reversing_a_run_posts_the_opposite_on_its_date_and_the_month_reads_fills_again(): void
    {
        ['runs' => $runs] = $this->patternWithRuns();
        $this->post(route('assets.depreciation.post'), ['period' => '2026-11'])->assertSessionHasNoErrors();
        $run = DepreciationRun::sole();
        $runs[1]->update(['moulds_made_qty' => 40]);                                           // the count corrected: 100 fills now

        $this->post(route('assets.runs.reverse', $run), ['reason' => 'Fills on a moulding run were wrong', 'detail' => 'MR-97 count corrected from 64 to 40'])
            ->assertSessionHasNoErrors();

        $this->assertSame([], $this->balances());
        $run->refresh();
        $this->assertSame([DepreciationRun::REVERSED, '2026-11-30'], [$run->status, $run->reversalJournal->journal_date->toDateString()]);
        $this->assertSame($run->journal_id, $run->reversalJournal->reverses_id);
        $this->get(route('assets.depreciation', ['period' => '2026-11']))->assertOk()->assertSee('Reversed by')->assertSee('24,800');
        $this->assertSame(24800.0, app(Depreciation::class)->forMonth(Carbon::parse('2026-11-01'))->sole()['charge']);

        $this->post(route('assets.depreciation.post'), ['period' => '2026-11'])->assertSessionHasNoErrors();
        $this->assertSame(['21-03-02' => -24800.0, '51-06-03' => 24800.0], $this->balances());
    }

    public function test_months_are_posted_in_order_and_only_once_they_have_ended(): void
    {
        FixedAsset::factory()->create();
        $this->post(route('assets.depreciation.post'), ['period' => '2026-09'])->assertSessionHasNoErrors();

        $this->get(route('assets.depreciation', ['period' => '2026-11']))->assertOk()->assertSee('Post October 2026 first.');
        $this->post(route('assets.depreciation.post'), ['period' => '2026-11'])->assertSessionHasErrors(['run' => 'Post October 2026 first.']);
        $this->post(route('assets.depreciation.post'), ['period' => '2026-12'])->assertSessionHasErrors(['run' => 'December ends on 31 Dec; post after the last shift.']);
        $this->assertSame(1, DepreciationRun::count());
    }

    public function test_an_asset_brought_in_with_depreciation_to_date_is_charged_only_after_that_date(): void
    {
        ['asset' => $asset] = $this->patternWithRuns();
        $asset->update(['opening_depreciation' => 7440, 'opening_fills' => 30, 'opening_to' => '2026-11-10']);   // MR-90 on 2 Nov is in the brought-in figures

        $november = app(Depreciation::class)->forMonth(Carbon::parse('2026-11-01'))->sole();
        $this->assertSame([64, 15872.0], [$november['fills'], $november['charge']]);
        $this->assertSame([], app(Depreciation::class)->forMonth(Carbon::parse('2026-10-01'))->all());
    }

    public function test_a_run_is_not_reversed_while_a_later_one_is_posted_or_its_month_is_closed(): void
    {
        FixedAsset::factory()->create();
        $this->post(route('assets.depreciation.post'), ['period' => '2026-10'])->assertSessionHasNoErrors();
        $this->post(route('assets.depreciation.post'), ['period' => '2026-11'])->assertSessionHasNoErrors();
        [$october, $november] = DepreciationRun::orderBy('period')->get()->all();

        $this->get(route('assets.runs.show', $october))->assertRedirect(route('assets.depreciation', ['period' => '2026-10']));
        $this->get(route('assets.depreciation', ['period' => '2026-10']))->assertOk()->assertSee('Runs are reversed newest first');
        $this->post(route('assets.runs.reverse', $october), ['reason' => 'Wrong'])->assertSessionHasErrors('run');
        $this->assertSame(DepreciationRun::POSTED, $october->fresh()->status);

        AppSetting::setValue(LedgerSettings::CLOSED_TO, '2026-11-30');
        $this->post(route('assets.runs.reverse', $november), ['reason' => 'Wrong'])->assertSessionHasErrors(['run' => 'November is closed; reopen it in month close first.']);
        $this->assertSame(DepreciationRun::POSTED, $november->fresh()->status);
    }
}
