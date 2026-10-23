<?php

namespace App\Services\Production;

use App\Models\Operation;
use App\Models\ProcessRoute;
use App\Models\ProductionStageDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The foundry's default route: the steps every casting follows after the pour, in order. Saving it also sets each library
 * step's order and on/off state, which the queue, the move form and the lot page read, so they follow the route.
 */
class ProcessRoutes
{
    public function defaultRoute(): ProcessRoute
    {
        return ProcessRoute::where('applies_to', 'DEFAULT')->first()
            ?? DB::transaction(function () {
                $route = ProcessRoute::create(['applies_to' => 'DEFAULT', 'code' => 'DEFAULT', 'name' => 'Our process', 'revision' => 'A', 'status' => 'ACTIVE', 'is_primary' => true]);
                $this->writeSteps($route, ProductionStageDefinition::where('is_active', true)->orderBy('sequence')->pluck('id')->all());

                return $route;
            });
    }

    /** @return Collection<int, Operation> the default route's steps in order, each with its library step */
    public function defaultSteps(): Collection
    {
        return $this->defaultRoute()->operations()->with('stage')->get();
    }

    /**
     * Puts the default route's steps in this order. A library step left out is switched off; it stays in the library and on
     * the lots that passed it.
     *
     * @param  list<string>  $stageIds
     */
    public function saveDefault(array $stageIds): void
    {
        $stageIds = array_values($stageIds);
        if (count($stageIds) !== count(array_unique($stageIds))) {
            throw new InvalidArgumentException('A step can only be on the route once.');
        }
        if (ProductionStageDefinition::whereIn('id', $stageIds)->count() !== count($stageIds)) {
            throw new InvalidArgumentException('One of the steps no longer exists.');
        }

        DB::transaction(function () use ($stageIds) {
            $this->writeSteps($this->defaultRoute(), $stageIds);

            // Order and on/off state onto the library. Two passes, because order and name are unique together.
            $offset = (int) ProductionStageDefinition::max('sequence') + 1000;
            foreach ($stageIds as $i => $id) {
                ProductionStageDefinition::whereKey($id)->update(['sequence' => $offset + $i]);
            }
            foreach ($stageIds as $i => $id) {
                ProductionStageDefinition::whereKey($id)->update(['sequence' => ($i + 1) * 10, 'is_active' => true]);
            }
            ProductionStageDefinition::whereNotIn('id', $stageIds)->where('is_active', true)->update(['is_active' => false]);
        });
    }

    /**
     * Adds a new step to the library, and to the end of the default route unless it is to go before a step.
     *
     * @param  array{name: string, kind: string, is_quality_gate?: bool, knocks_out_mould?: bool, records_weight?: bool, description?: ?string}  $data
     */
    public function addStep(array $data): ProductionStageDefinition
    {
        $base = Str::of($data['name'])->upper()->replaceMatches('/[^A-Z0-9]+/', '_')->trim('_')->limit(40, '')->value() ?: 'STEP';
        $code = $base;
        for ($n = 2; ProductionStageDefinition::where('code', $code)->exists(); $n++) {
            $code = $base.'_'.$n;
        }

        return DB::transaction(function () use ($data, $code) {
            $stage = ProductionStageDefinition::create($data + [
                'code' => $code, 'sequence' => (int) ProductionStageDefinition::max('sequence') + 10, 'is_active' => true,
            ]);
            $this->saveDefault([...$this->defaultSteps()->pluck('stage_definition_id')->all(), $stage->id]);

            return $stage;
        });
    }

    /** @param list<string> $stageIds */
    private function writeSteps(ProcessRoute $route, array $stageIds): void
    {
        $route->operations()->delete();
        $stages = ProductionStageDefinition::whereIn('id', $stageIds)->get()->keyBy('id');
        foreach ($stageIds as $i => $id) {
            $stage = $stages[$id];
            Operation::create([
                'process_route_id' => $route->id, 'stage_definition_id' => $id, 'sequence' => $i + 1,
                'code' => $stage->code, 'name' => $stage->name, 'operation_type' => $stage->kind ?? 'PROCESS',
            ]);
        }
    }
}
