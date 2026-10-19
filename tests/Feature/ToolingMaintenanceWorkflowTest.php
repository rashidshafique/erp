<?php

namespace Tests\Feature;

use App\Models\Pattern;
use App\Models\ToolingMaintenanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ToolingMaintenanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function createPattern(): Pattern
    {
        return Pattern::create([
            'code' => 'WF-'.Str::upper(Str::random(8)),
            'name' => 'Workflow Test Pattern',
        ]);
    }

    private function createMaintenanceRecord(
        Pattern $pattern,
        array $overrides = []
    ): ToolingMaintenanceRecord {
        $id = (string) Str::uuid();

        DB::table('tooling_maintenance_records')->insert(array_merge([
            'id' => $id,
            'tooling_type' => 'PATTERN',
            'pattern_id' => $pattern->id,
            'record_number' => 'TM-'.Str::upper(Str::random(10)),
            'activity_type' => 'INSPECTION',
            'status' => 'OPEN',
            'priority' => 'NORMAL',
            'reported_at' => now(),
            'labour_hours' => 0,
            'parts_cost' => 0,
            'other_cost' => 0,
            'downtime_hours' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return ToolingMaintenanceRecord::findOrFail($id);
    }

    public function test_photos_attach_to_a_maintenance_record_through_the_shared_files(): void
    {
        Storage::fake(config('foundry.attachments.disk'));

        $pattern = $this->createPattern();
        $record = $this->createMaintenanceRecord($pattern);
        $photo = new UploadedFile(base_path('tests/Fixtures/test-image.png'), 'pattern-damage.png', 'image/png', null, true);

        $this->post(route('attachments.store', ['tooling_maintenance', $record->id]), [
            'files' => [$photo],
            'category' => 'PHOTO',
            'title' => 'Visible crack near the cavity',
        ])->assertSessionHasNoErrors();

        $attachment = $record->attachments()->firstOrFail();
        $this->assertSame('pattern-damage.png', $attachment->original_name);
        Storage::disk(config('foundry.attachments.disk'))->assertExists($attachment->path);
        $this->get(route('tooling-maintenance.show', $record))->assertOk()->assertSee('Visible crack near the cavity');
    }

    public function test_old_maintenance_photos_move_to_the_private_disk(): void
    {
        Storage::fake('public');
        Storage::fake(config('foundry.attachments.disk'));

        $pattern = $this->createPattern();
        $record = $this->createMaintenanceRecord($pattern);
        $oldPath = 'tooling-maintenance/'.$record->id.'/crack.jpg';
        Storage::disk('public')->put($oldPath, 'fake image content');
        $id = (string) Str::uuid();
        DB::table('tooling_maintenance_attachments')->insert([
            'id' => $id, 'tooling_maintenance_record_id' => $record->id, 'disk' => 'public', 'path' => $oldPath,
            'original_filename' => 'crack.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 18, 'caption' => 'Crack', 'uploaded_by' => 'Fitter',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration = require app_path('Domains/Tooling/database/migrations/2026_10_22_100000_move_tooling_maintenance_photos_to_attachments.php');
        $migration->up();
        $migration->up();   // running again does not copy twice

        $moved = $record->attachments()->sole();
        $this->assertSame($id, $moved->id);
        $this->assertSame(['PHOTO', 'Crack', 'crack.jpg', 'Fitter'], [$moved->category, $moved->title, $moved->original_name, $moved->uploaded_by_name]);
        $this->assertSame(hash('sha256', 'fake image content'), $moved->sha256);
        Storage::disk(config('foundry.attachments.disk'))->assertExists($moved->path);
        Storage::disk('public')->assertMissing($oldPath);
        $this->get(route('attachments.download', $moved))->assertOk();
    }

    public function test_history_page_shows_the_selected_tooling_history(): void
    {
        $pattern = $this->createPattern();

        $first = $this->createMaintenanceRecord($pattern, [
            'parts_cost' => 1250,
            'downtime_hours' => 2,
        ]);

        $second = $this->createMaintenanceRecord($pattern, [
            'activity_type' => 'REPAIR',
            'status' => 'COMPLETED',
            'parts_cost' => 750,
            'downtime_hours' => 3,
        ]);

        $response = $this->get(
            route('tooling-maintenance.history', $first)
        );

        $response->assertOk();

        $response->assertViewHas('summary', function (array $summary): bool {
            return $summary['total_records'] === 2
                && $summary['completed_records'] === 1
                && (float) $summary['downtime_hours'] === 5.0
                && (float) $summary['parts_cost'] === 2000.0;
        });
    }

    public function test_dashboard_counts_active_jobs_and_excludes_completed_jobs_from_overdue(): void
    {
        $pattern = $this->createPattern();

        $this->createMaintenanceRecord($pattern, [
            'status' => 'OPEN',
            'priority' => 'URGENT',
            'activity_type' => 'BREAKDOWN',
            'next_inspection_due_on' => today()->subDay()->toDateString(),
            'downtime_hours' => 2,
            'parts_cost' => 500,
        ]);

        $this->createMaintenanceRecord($pattern, [
            'status' => 'COMPLETED',
            'priority' => 'HIGH',
            'next_inspection_due_on' => today()->subDay()->toDateString(),
            'downtime_hours' => 1,
            'other_cost' => 200,
        ]);

        $response = $this->get(route('tooling-maintenance.dashboard'));

        $response->assertOk();

        $response->assertViewHas('openCount', 1);
        $response->assertViewHas('urgentCount', 1);
        $response->assertViewHas('breakdownCount', 1);
        $response->assertViewHas('overdueCount', 1);
        $response->assertViewHas('downtimeHours', 3.0);
        $response->assertViewHas('maintenanceSpend', 700.0);
    }
}
