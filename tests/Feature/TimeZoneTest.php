<?php

namespace Tests\Feature;

use App\Models\Furnace;
use App\Models\Heat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Times must mean the same instant whatever zone the server, database or app is set to. */
class TimeZoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_session_follows_the_app_zone_and_times_round_trip(): void
    {
        $this->assertSame(config('app.timezone'), config('database.connections.pgsql.timezone'));

        $f = Furnace::create(['code' => 'IF-T', 'name' => 'Induction', 'furnace_type' => 'INDUCTION', 'status' => 'ACTIVE']);
        $heat = Heat::create(['heat_number' => 'H-TZ', 'furnace_id' => $f->id, 'process_type' => 'INDUCTION', 'status' => 'PLANNED']);
        $at = now()->subMinutes(5)->startOfSecond();
        $t = $heat->treatments()->create(['treatment_type' => 'MG_TREATMENT', 'treated_at' => $at]);

        $this->assertSame($at->getTimestamp(), $t->fresh()->treated_at->getTimestamp());
        $this->assertEquals(5, (int) $t->fresh()->treated_at->diffInMinutes(now()));
    }
}
