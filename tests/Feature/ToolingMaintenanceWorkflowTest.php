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
            'code' => 'WF-' . Str::upper(Str::random(8)),
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
            'record_number' => 'TM-' . Str::upper(Str::random(10)),
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

    public function test_photo_upload_creates_attachment_and_stores_file(): void
    {
        Storage::fake('public');

        $pattern = $this->createPattern();
        $record = $this->createMaintenanceRecord($pattern);
        $photo = new UploadedFile(
            base_path('tests/Fixtures/test-image.png'),
            'pattern-damage.png',
            'image/png',
            null,
            true
        );

        $response = $this->post(
            route('tooling-maintenance.attachments.store', $record),
            [
                'photos' => [$photo],
                'captions' => ['Visible crack near the cavity'],
                'uploaded_by' => 'Foundry maintenance',
            ]
        );

        $response->assertRedirect(
            route('tooling-maintenance.show', $record)
        );

        $this->assertDatabaseHas('tooling_maintenance_attachments', [
            'tooling_maintenance_record_id' => $record->id,
            'original_filename' => 'pattern-damage.png',
            'caption' => 'Visible crack near the cavity',
            'uploaded_by' => 'Foundry maintenance',
            'disk' => 'public',
        ]);

        $attachment = $record->attachments()->firstOrFail();

        Storage::disk('public')->assertExists($attachment->path);
    }

    public function test_photo_upload_requires_at_least_one_photo(): void
    {
        Storage::fake('public');

        $pattern = $this->createPattern();
        $record = $this->createMaintenanceRecord($pattern);

        $response = $this->from(
            route('tooling-maintenance.show', $record)
        )->post(
            route('tooling-maintenance.attachments.store', $record),
            ['photos' => []]
        );

        $response->assertSessionHasErrors('photos');

        $this->assertDatabaseCount('tooling_maintenance_attachments', 0);
    }

    public function test_photo_can_be_deleted_from_its_maintenance_record(): void
    {
        Storage::fake('public');

        $pattern = $this->createPattern();
        $record = $this->createMaintenanceRecord($pattern);

        $path = 'tooling-maintenance/' . $record->id . '/test-photo.jpg';

        Storage::disk('public')->put($path, 'fake image content');

        $attachment = $record->attachments()->create([
            'disk' => 'public',
            'path' => $path,
            'original_filename' => 'test-photo.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 18,
            'caption' => 'Test photo',
        ]);

        $response = $this->delete(
            route('tooling-maintenance.attachments.destroy', [
                'toolingMaintenance' => $record,
                'attachment' => $attachment,
            ])
        );

        $response->assertRedirect(
            route('tooling-maintenance.show', $record)
        );

        $this->assertDatabaseMissing(
            'tooling_maintenance_attachments',
            ['id' => $attachment->id]
        );

        Storage::disk('public')->assertMissing($path);
    }

    public function test_attachment_cannot_be_deleted_through_another_record(): void
    {
        Storage::fake('public');

        $firstPattern = $this->createPattern();
        $secondPattern = $this->createPattern();

        $firstRecord = $this->createMaintenanceRecord($firstPattern);
        $secondRecord = $this->createMaintenanceRecord($secondPattern);

        $path = 'tooling-maintenance/' . $firstRecord->id . '/protected.jpg';

        Storage::disk('public')->put($path, 'fake image content');

        $attachment = $firstRecord->attachments()->create([
            'disk' => 'public',
            'path' => $path,
            'original_filename' => 'protected.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 18,
        ]);

        $response = $this->delete(
            route('tooling-maintenance.attachments.destroy', [
                'toolingMaintenance' => $secondRecord,
                'attachment' => $attachment,
            ])
        );

        $response->assertNotFound();

        $this->assertDatabaseHas('tooling_maintenance_attachments', [
            'id' => $attachment->id,
        ]);

        Storage::disk('public')->assertExists($path);
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