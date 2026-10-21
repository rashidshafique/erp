<?php

namespace Tests\Feature;

use App\Models\CastingLot;
use App\Models\CastingLotStageEvent;
use App\Models\ProductionStageDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CastingLotCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_next_code_starts_each_pour_date_at_one(): void
    {
        $this->assertSame('261021-01', CastingLot::nextCode(new \DateTimeImmutable('2026-10-21')));
        $this->assertSame('261022-01', CastingLot::nextCode(new \DateTimeImmutable('2026-10-22')));
    }

    public function test_label_falls_back_to_lot_number_without_a_code(): void
    {
        $this->assertSame('261021-03', (new CastingLot(['code' => '261021-03', 'lot_number' => 'LOT-X-01']))->label);
        $this->assertSame('LOT-X-01', (new CastingLot(['lot_number' => 'LOT-X-01']))->label);
    }

    public function test_stages_summary_lists_stages_in_process_order_with_rejections(): void
    {
        $stage = fn (int $sequence, string $name) => (new ProductionStageDefinition(['name' => $name, 'sequence' => $sequence]));
        $event = fn (string $stageId, ProductionStageDefinition $stage, int $rejected) => (new CastingLotStageEvent(['stage_definition_id' => $stageId, 'quantity_rejected' => $rejected]))->setRelation('stage', $stage);
        $fettling = $stage(2, 'Fettling');
        $shakeout = $stage(1, 'Shakeout');

        $lot = (new CastingLot)->setRelation('stageEvents', collect([$event('b', $fettling, 2), $event('a', $shakeout, 0)]));

        $this->assertSame('Shakeout → Fettling (2 rej)', $lot->stages_summary);
        $this->assertSame('—', (new CastingLot)->setRelation('stageEvents', collect())->stages_summary);
    }
}
