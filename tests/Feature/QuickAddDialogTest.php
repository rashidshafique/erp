<?php

namespace Tests\Feature;

use App\Models\MouldingStation;
use App\Models\MouldingTeam;
use App\Models\QualityDefect;
use App\Models\SandSystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QuickAddDialogTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_pages_and_dropdowns_offer_the_quick_add_dialogs(): void
    {
        $this->get(route('defects.index'))->assertOk()
            ->assertSee('<dialog id="qd-defect"', false)->assertSee('data-quick="qd-defect"', false);

        $this->get(route('moulding-teams.index'))->assertOk()
            ->assertSee('<dialog id="qd-team"', false)->assertSee('<dialog id="qd-station"', false)->assertSee('<dialog id="qd-sand"', false)
            ->assertSee('data-quick="qd-team"', false)->assertSee('data-quick="qd-station"', false);

        $this->get(route('patterns.create'))->assertOk()
            ->assertSee('<dialog id="qd-sand"', false)->assertSee('data-quick-target="sand_system" data-quick-value="label"', false);
    }

    /** @return array<string, array{string, array<string, mixed>, string}> */
    public static function quickAdds(): array
    {
        return [
            'defect' => ['defects.store', ['code' => 'qa-1', 'name' => 'Blowhole', 'category' => 'GAS', 'default_severity' => 'MAJOR'], 'GAS · Blowhole'],
            'team' => ['moulding-teams.teams.store', ['code' => 'T9', 'name' => 'Night', 'lead_name' => 'Asif', 'member_count' => 3, 'status' => 'ACTIVE'], 'T9 — Asif'],
            'station' => ['moulding-teams.stations.store', ['code' => 'MP-9', 'name' => 'Bench 9', 'station_type' => 'FLOOR', 'status' => 'ACTIVE'], 'MP-9 — Bench 9'],
        ];
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('quickAdds')]
    public function test_a_quick_add_asking_for_json_gets_the_new_option(string $route, array $payload, string $label): void
    {
        $response = $this->postJson(route($route), $payload + ['_quick' => 'qd-x']);

        $model = match ($route) {
            'defects.store' => QualityDefect::where('code', 'QA-1')->sole(),
            'moulding-teams.teams.store' => MouldingTeam::where('code', 'T9')->sole(),
            'moulding-teams.stations.store' => MouldingStation::where('code', 'MP-9')->sole(),
        };
        $response->assertCreated()->assertExactJson(['id' => $model->id, 'label' => $label]);
    }

    public function test_a_quick_added_sand_system_keeps_its_generated_code(): void
    {
        $response = $this->postJson(route('sand-systems.store'), ['name' => 'No-bake', 'system_type' => 'CHEMICAL_BONDED', 'code' => 'MINE']);

        $sand = SandSystem::where('name', 'No-bake')->sole();
        $response->assertCreated()->assertExactJson(['id' => $sand->id, 'label' => 'No-bake']);
        $this->assertSame('NO_BAKE', $sand->code);
    }

    public function test_the_full_page_form_still_redirects_back(): void
    {
        $response = $this->from(route('defects.index'))->post(route('defects.store'), ['code' => 'QA-1', 'name' => 'Blowhole', 'category' => 'GAS', 'default_severity' => 'MAJOR', '_quick' => 'qd-defect']);

        $response->assertRedirect(route('defects.index'))->assertSessionHas('success', 'Defect added.');
        $this->assertModelExists(QualityDefect::where('code', 'QA-1')->sole());
    }

    public function test_a_validation_error_reopens_its_dialog_with_the_input_kept(): void
    {
        // Asserting on the session here would read the flashed errors before the page does, so the page shows the outcome.
        $this->from(route('defects.index'))->post(route('defects.store'), ['code' => 'SAND-INCL', 'name' => 'Second sand inclusion', 'category' => 'SAND', 'default_severity' => 'MAJOR', '_quick' => 'qd-defect']);

        $page = $this->get(route('defects.index'));

        $page->assertOk()->assertSee('data-reopen=', false)->assertSee('value="Second sand inclusion"', false)->assertSee('The code has already been taken.');
        $this->assertSame(0, QualityDefect::where('name', 'Second sand inclusion')->count());
    }

    public function test_an_error_from_the_inline_form_does_not_open_the_dialog(): void
    {
        $this->from(route('defects.index'))->post(route('defects.store'), ['code' => 'SAND-INCL', 'name' => 'Inline name', 'category' => 'SAND', 'default_severity' => 'MAJOR']);

        $page = $this->get(route('defects.index'));

        $page->assertOk()->assertSee('The code has already been taken.')->assertDontSee('data-reopen=', false)->assertDontSee('value="Inline name"', false);
    }

    /** @return array<string, array{string, array<string, mixed>}> */
    public static function duplicateCodes(): array
    {
        return [
            'defect, in any case' => ['defects.store', ['code' => 'sand-incl', 'name' => 'Again', 'category' => 'SAND', 'default_severity' => 'MAJOR']],
            'team' => ['moulding-teams.teams.store', ['code' => 'T1', 'name' => 'Again', 'lead_name' => 'Bilal', 'member_count' => 2, 'status' => 'ACTIVE']],
            'station' => ['moulding-teams.stations.store', ['code' => 'MP-1', 'name' => 'Again', 'station_type' => 'FLOOR', 'status' => 'ACTIVE']],
        ];
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('duplicateCodes')]
    public function test_a_duplicate_code_is_refused(string $route, array $payload): void
    {
        MouldingTeam::create(['code' => 'T1', 'name' => 'Day', 'lead_name' => 'Rashid', 'member_count' => 5, 'status' => 'ACTIVE']);
        MouldingStation::create(['code' => 'MP-1', 'name' => 'Bench 1', 'station_type' => 'FLOOR', 'status' => 'ACTIVE', 'max_patterns' => 4]);
        $counts = [QualityDefect::count(), MouldingTeam::count(), MouldingStation::count()];

        $response = $this->postJson(route($route), $payload);

        $response->assertUnprocessable()->assertJsonValidationErrors(['code' => 'The code has already been taken.']);
        $this->assertSame($counts, [QualityDefect::count(), MouldingTeam::count(), MouldingStation::count()]);
    }

    public function test_a_duplicate_sand_system_name_is_refused(): void
    {
        $count = SandSystem::count();

        $response = $this->postJson(route('sand-systems.store'), ['name' => 'greensand', 'system_type' => 'CLAY_BONDED']);

        $response->assertUnprocessable()->assertJsonValidationErrors(['name' => 'There is already a sand system called greensand.']);
        $this->assertSame($count, SandSystem::count());
    }
}
