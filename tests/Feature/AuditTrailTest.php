<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Furnace;
use App\Models\Heat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private function heat(): Heat
    {
        $f = Furnace::create(['code' => 'IF-1', 'name' => 'Induction', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);

        return Heat::create(['heat_number' => 'H-AUD1', 'furnace_id' => $f->id, 'process_type' => 'INDUCTION', 'status' => 'PLANNED']);
    }

    public function test_create_update_and_delete_are_recorded_with_old_and_new_values(): void
    {
        $heat = $this->heat();
        $created = AuditLog::where('entity_type', 'Heat')->where('entity_id', $heat->id)->where('action', 'CREATED')->firstOrFail();
        $this->assertSame('H-AUD1', $created->entity_label);
        $this->assertSame('PLANNED', $created->changeSet()['status']['new']);

        $heat->update(['status' => 'IN_PROGRESS']);
        $upd = AuditLog::where('entity_id', $heat->id)->where('action', 'UPDATED')->firstOrFail();
        $this->assertEquals(['old' => 'PLANNED', 'new' => 'IN_PROGRESS'], $upd->changeSet()['status']);
        $this->assertArrayNotHasKey('updated_at', $upd->changeSet());

        $heat->update(['status' => 'IN_PROGRESS']);   // no change, nothing new to record
        $this->assertSame(1, AuditLog::where('entity_id', $heat->id)->where('action', 'UPDATED')->count());
    }

    public function test_query_builder_writes_are_recorded_too(): void
    {
        $heat = $this->heat();
        $this->post(route('custom-fields.store'), ['entity_type' => 'heat', 'label' => 'Ladle', 'data_type' => 'TEXT']);
        $this->post(route('custom-fields.values', ['heat', $heat->id]), ['cf' => ['ladle' => 'L-9']])->assertSessionHasNoErrors();
        $e = AuditLog::where('entity_type', 'Heat')->where('entity_id', $heat->id)->where('action', 'UPDATED')->latest('id')->firstOrFail();
        $this->assertSame('L-9', $e->changeSet()['custom_fields.ladle']['new']);
    }

    public function test_history_and_viewer_pages_render(): void
    {
        $heat = $this->heat();
        $heat->update(['status' => 'IN_PROGRESS']);
        $this->get(route('heats.show', $heat))->assertOk()->assertSee('History')->assertSee('IN_PROGRESS');
        $this->get(route('audit.index', ['entity_type' => 'Heat', 'q' => 'H-AUD1']))->assertOk()->assertSee('H-AUD1')->assertSee('CREATED');
        $this->get(route('audit.index', ['action' => 'DELETED', 'q' => 'H-AUD1']))->assertOk()->assertDontSee('IN_PROGRESS');
    }

    public function test_history_cannot_be_changed_and_the_chain_verifies(): void
    {
        $heat = $this->heat();
        $heat->update(['status' => 'IN_PROGRESS']);
        $this->assertSame(0, Artisan::call('audit:verify'));

        $threw = false;
        try {
            DB::transaction(fn () => DB::table('audit_logs')->update(['user_name' => 'forged']));
        } catch (\Throwable $e) {
            $threw = str_contains($e->getMessage(), 'append-only');
        }
        $this->assertTrue($threw, 'updating the audit log must be refused');
        $threw = false;
        try {
            DB::transaction(fn () => DB::table('audit_logs')->delete());
        } catch (\Throwable $e) {
            $threw = str_contains($e->getMessage(), 'append-only');
        }
        $this->assertTrue($threw, 'deleting from the audit log must be refused');
    }

    public function test_verify_detects_a_forged_row_and_a_missing_row(): void
    {
        $heat = $this->heat();
        $heat->update(['status' => 'IN_PROGRESS']);
        $heat->update(['status' => 'COMPLETED']);
        // Simulate someone with superuser rights switching the trigger off and editing history.
        DB::unprepared('ALTER TABLE audit_logs DISABLE TRIGGER USER');
        $mid = AuditLog::where('action', 'UPDATED')->orderBy('id')->first();
        DB::table('audit_logs')->where('id', $mid->id)->update(['entity_label' => 'FORGED']);
        $this->assertSame(1, Artisan::call('audit:verify'));
        DB::table('audit_logs')->where('id', $mid->id)->update(['entity_label' => $mid->entity_label]);
        $this->assertSame(0, Artisan::call('audit:verify'));
        DB::table('audit_logs')->where('id', $mid->id)->delete();
        $this->assertSame(1, Artisan::call('audit:verify'));
        DB::unprepared('ALTER TABLE audit_logs ENABLE TRIGGER USER');
    }
}
