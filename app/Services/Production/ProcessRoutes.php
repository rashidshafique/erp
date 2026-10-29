<?php

namespace App\Services\Production;

use App\Models\Operation;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Models\ProcessRoute;
use App\Models\ProductionStageDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The routes castings follow after the pour. The foundry's default route is the steps every casting follows, in order; saving
 * it also sets each library step's order and on/off state. A product can have its own route instead, copied from the default
 * and then changed; it leaves the library alone. stepsFor() is the one place that says which steps a lot goes through.
 */
class ProcessRoutes
{
    /** @var array<string, Collection<int, ProductionStageDefinition>> */
    private array $resolved = [];

    /**
     * The steps a casting of this product revision goes through, in order: its product's own route when it has one, else the
     * default route's steps plus the product-specific steps ticked on the revision.
     *
     * @return Collection<int, ProductionStageDefinition>
     */
    public function stepsFor(?string $productRevisionId): Collection
    {
        return $this->resolved[$productRevisionId ?? ''] ??= (function () use ($productRevisionId) {
            $productId = $productRevisionId ? ProductRevision::whereKey($productRevisionId)->value('product_id') : null;
            $route = $productId ? $this->productRoute($productId) : null;
            if ($route) {
                return $route->operations()->with('stage')->get()->map->stage->filter()->values();
            }

            return ProductionStageDefinition::where('is_active', true)->applicableTo($productRevisionId)->orderBy('sequence')->orderBy('name')->get();
        })();
    }

    /** Where a step sits in a list of steps (0 first), or null when it is not on it. */
    public static function position(Collection $steps, ?string $stageId): ?int
    {
        $at = $steps->search(fn ($s) => $s->id === $stageId);

        return $at === false ? null : $at;
    }

    public function productRoute(Product|string $product): ?ProcessRoute
    {
        return ProcessRoute::where('applies_to', 'PRODUCT')->where('product_id', $product instanceof Product ? $product->id : $product)->first();
    }

    /** Gives the product its own route, starting as a copy of the default route's steps. */
    public function customise(Product $product): ProcessRoute
    {
        return $this->productRoute($product) ?? DB::transaction(function () use ($product) {
            $route = ProcessRoute::create(['applies_to' => 'PRODUCT', 'product_id' => $product->id, 'code' => Str::limit('P-'.$product->code, 40, ''), 'name' => 'Route for '.$product->code,
                'revision' => 'A', 'status' => 'ACTIVE', 'is_primary' => true]);
            $this->writeSteps($route, $this->defaultSteps()->pluck('stage_definition_id')->all());
            $this->resolved = [];

            return $route;
        });
    }

    /**
     * Puts the product's own route's steps in this order. Any library step can be used, including ones not on the default route.
     *
     * @param  list<string>  $stageIds
     */
    public function saveForProduct(Product $product, array $stageIds): void
    {
        $stageIds = $this->checked($stageIds);
        DB::transaction(fn () => $this->writeSteps($this->customise($product), $stageIds));
        $this->resolved = [];
    }

    /** The product follows the default route again. Lots keep what they recorded. */
    public function resetToDefault(Product $product): void
    {
        $this->productRoute($product)?->delete();
        $this->resolved = [];
    }

    /**
     * @param  list<string>  $stageIds
     * @return list<string>
     */
    private function checked(array $stageIds): array
    {
        $stageIds = array_values($stageIds);
        if (count($stageIds) !== count(array_unique($stageIds))) {
            throw new InvalidArgumentException('A step can only be on the route once.');
        }
        if (ProductionStageDefinition::whereIn('id', $stageIds)->count() !== count($stageIds)) {
            throw new InvalidArgumentException('One of the steps no longer exists.');
        }

        return $stageIds;
    }

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
        $stageIds = $this->checked($stageIds);
        $this->resolved = [];

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
