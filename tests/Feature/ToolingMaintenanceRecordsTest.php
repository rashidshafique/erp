<?php

namespace Tests\Feature;

use App\Models\CoreBox;
use App\Models\CoreDefinition;
use App\Models\MouldAssembly;
use App\Models\MouldConfiguration;
use App\Models\Pattern;
use App\Models\PatternRevision;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ToolingMaintenanceRecordsTest extends TestCase
{
    use RefreshDatabase;

    private function maintenanceData(array $overrides = []): array
    {
        return array_merge([
            'id' => (string) Str::uuid(),
            'tooling_type' => 'PATTERN',
            'record_number' => 'TM-' . Str::upper(Str::random(10)),
            'activity_type' => 'INSPECTION',
            'status' => 'OPEN',
            'priority' => 'NORMAL',
            'labour_hours' => 0,
            'parts_cost' => 0,
            'other_cost' => 0,
            'downtime_hours' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }

    public function test_maintenance_record_can_reference_an_existing_pattern(): void
    {
        $pattern = Pattern::create([
            'code' => 'TM-PAT-' . Str::upper(Str::random(6)),
            'name' => 'Maintenance Test Pattern',
        ]);

        $data = $this->maintenanceData([
            'pattern_id' => $pattern->id,
        ]);

        DB::table('tooling_maintenance_records')->insert($data);

        $this->assertDatabaseHas('tooling_maintenance_records', [
            'id' => $data['id'],
            'pattern_id' => $pattern->id,
        ]);
    }

    
public function test_maintenance_record_must_reference_exactly_one_tooling_item(): void
{
    $connection = DB::connection();

    // Test zero tooling references.
    $connection->statement('SAVEPOINT tooling_check_zero');

    try {
        DB::table('tooling_maintenance_records')->insert(
            $this->maintenanceData()
        );

        $this->fail('A record without a tooling reference was accepted.');
    } catch (QueryException $exception) {
        $this->assertStringContainsString(
            'tooling_maintenance_records_exactly_one_tooling_check',
            $exception->getMessage()
        );
    } finally {
        $connection->statement('ROLLBACK TO SAVEPOINT tooling_check_zero');
        $connection->statement('RELEASE SAVEPOINT tooling_check_zero');
    }

    $pattern = Pattern::create([
        'code' => 'TM-PAT-' . Str::upper(Str::random(6)),
        'name' => 'Multiple Reference Test Pattern',
    ]);

    $core = CoreDefinition::create([
        'code' => 'TM-CORE-' . Str::upper(Str::random(6)),
        'name' => 'Multiple Reference Test Core',
        'core_type' => 'STANDARD',
    ]);

    // Test two tooling references.
    $connection->statement('SAVEPOINT tooling_check_multiple');

    try {
        DB::table('tooling_maintenance_records')->insert(
            $this->maintenanceData([
                'pattern_id' => $pattern->id,
                'core_definition_id' => $core->id,
            ])
        );

        $this->fail('A record with multiple tooling references was accepted.');
    } catch (QueryException $exception) {
        $this->assertStringContainsString(
            'tooling_maintenance_records_exactly_one_tooling_check',
            $exception->getMessage()
        );
    } finally {
        $connection->statement('ROLLBACK TO SAVEPOINT tooling_check_multiple');
        $connection->statement('RELEASE SAVEPOINT tooling_check_multiple');
    }
}



    public function test_maintenance_record_rejects_a_nonexistent_pattern(): void
    {
        $this->expectException(QueryException::class);

        DB::table('tooling_maintenance_records')->insert(
            $this->maintenanceData([
                'pattern_id' => (string) Str::uuid(),
            ])
        );
    }

    
public function test_pattern_cannot_be_deleted_while_maintenance_history_exists(): void
{
    $pattern = Pattern::create([
        'code' => 'TM-PAT-' . Str::upper(Str::random(6)),
        'name' => 'Protected Maintenance Pattern',
    ]);

    DB::table('tooling_maintenance_records')->insert(
        $this->maintenanceData([
            'pattern_id' => $pattern->id,
        ])
    );

    // Confirm the maintenance record really exists.
    $this->assertDatabaseHas('tooling_maintenance_records', [
        'pattern_id' => $pattern->id,
    ]);

    // Confirm the database can see the foreign-key relationship.
    $foreignKeyCheck = DB::selectOne(
        'SELECT COUNT(*) AS total
         FROM tooling_maintenance_records
         WHERE pattern_id = ?',
        [$pattern->id]
    );

    $this->assertEquals(1, (int) $foreignKeyCheck->total);

    // The database must reject deletion of the referenced pattern.
    $this->expectException(QueryException::class);

    $pattern->forceDelete();
}


    public function test_pattern_revision_relationship_is_available(): void
    {
        $pattern = Pattern::create([
            'code' => 'TM-PAT-' . Str::upper(Str::random(6)),
            'name' => 'Revision Relationship Pattern',
        ]);

        $revision = PatternRevision::create([
            'pattern_id' => $pattern->id,
            'revision' => 'A',
        ]);

        $this->assertTrue($revision->pattern->is($pattern));
    }

    public function test_core_box_and_mould_tooling_can_be_created_for_future_coverage(): void
    {
        $core = CoreDefinition::create([
            'code' => 'TM-CORE-' . Str::upper(Str::random(6)),
            'name' => 'Maintenance Test Core',
            'core_type' => 'STANDARD',
        ]);

        $box = CoreBox::create([
            'core_definition_id' => $core->id,
            'code' => 'TM-BOX-' . Str::upper(Str::random(6)),
            'name' => 'Maintenance Test Core Box',
        ]);

        $configuration = MouldConfiguration::create([
            'code' => 'TM-MOULD-' . Str::upper(Str::random(6)),
            'name' => 'Maintenance Test Configuration',
            'mould_type' => 'COPE_DRAG',
            'nominal_layer_count' => 2,
        ]);

        $assembly = MouldAssembly::create([
            'assembly_number' => 'TM-ASSY-' . Str::upper(Str::random(6)),
            'mould_configuration_id' => $configuration->id,
        ]);

        $this->assertTrue($box->coreDefinition->is($core));
        $this->assertTrue($assembly->configuration->is($configuration));
    }
}
