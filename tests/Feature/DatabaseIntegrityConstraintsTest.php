<?php

namespace Tests\Feature;

use App\Models\ManufacturingMethod;
use App\Models\Operation;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Models\ProcessRoute;
use App\Models\WorkCenter;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DatabaseIntegrityConstraintsTest extends TestCase
{
    use RefreshDatabase;

    private function makeProductRevision(string $code): array
    {
        $product = Product::create([
            'code' => $code,
            'name' => "Test Product {$code}",
        ]);

        $revision = ProductRevision::create([
            'product_id' => $product->id,
            'revision' => 'A',
        ]);

        return [$product, $revision];
    }

    private function makeMethod(
        ProductRevision $revision,
        string $code
    ): ManufacturingMethod {
        return ManufacturingMethod::create([
            'product_revision_id' => $revision->id,
            'code' => $code,
            'name' => "Test Method {$code}",
        ]);
    }

    private function makeRoute(
        ManufacturingMethod $method,
        string $code
    ): ProcessRoute {
        return ProcessRoute::create([
            'manufacturing_method_id' => $method->id,
            'code' => $code,
            'name' => "Test Route {$code}",
        ]);
    }

    private function makeOperation(ProcessRoute $route): Operation
    {
        $workCenter = WorkCenter::create([
            'code' => 'WC-' . Str::upper(Str::random(8)),
            'name' => 'Test Work Center',
            'work_center_type' => 'MOULDING',
        ]);

        return Operation::create([
            'process_route_id' => $route->id,
            'work_center_id' => $workCenter->id,
            'sequence' => 10,
            'code' => 'OP-' . Str::upper(Str::random(6)),
            'name' => 'Test Operation',
            'operation_type' => 'MOULDING',
        ]);
    }

    public function test_production_order_cannot_use_another_products_revision(): void
    {
        [$productA] = $this->makeProductRevision('INT-001');
        [$productB, $revisionB] = $this->makeProductRevision('INT-002');

        $this->expectException(QueryException::class);

        DB::table('production_orders')->insert([
            'id' => (string) Str::uuid(),
            'production_order_number' => 'TEST-INT-001',
            'product_id' => $productA->id,
            'product_revision_id' => $revisionB->id,
            'planned_qty' => 1,
        ]);
    }

    public function test_production_order_cannot_combine_a_route_with_the_wrong_method(): void
    {
        [, $revision] = $this->makeProductRevision('INT-003');

        $methodA = $this->makeMethod($revision, 'METHOD-A');
        $methodB = $this->makeMethod($revision, 'METHOD-B');
        $routeB = $this->makeRoute($methodB, 'ROUTE-B');

        $product = $revision->product;

        $this->expectException(QueryException::class);

        DB::table('production_orders')->insert([
            'id' => (string) Str::uuid(),
            'production_order_number' => 'TEST-INT-002',
            'product_id' => $product->id,
            'product_revision_id' => $revision->id,
            'manufacturing_method_id' => $methodA->id,
            'process_route_id' => $routeB->id,
            'planned_qty' => 1,
        ]);
    }

    public function test_control_plan_cannot_use_an_operation_from_another_route(): void
    {
        [, $revision] = $this->makeProductRevision('INT-004');

        $method = $this->makeMethod($revision, 'METHOD-C');
        $routeA = $this->makeRoute($method, 'ROUTE-C1');
        $routeB = $this->makeRoute($method, 'ROUTE-C2');

        $operationA = $this->makeOperation($routeA);

        $this->expectException(QueryException::class);

        DB::table('process_control_plans')->insert([
            'id' => (string) Str::uuid(),
            'plan_number' => 'TEST-INT-003',
            'title' => 'Invalid route-operation combination',
            'manufacturing_method_id' => $method->id,
            'process_route_id' => $routeB->id,
            'operation_id' => $operationA->id,
        ]);
    }

    public function test_inspection_plan_cannot_use_a_specification_for_another_revision(): void
    {
        [, $revisionA] = $this->makeProductRevision('INT-005');
        [, $revisionB] = $this->makeProductRevision('INT-006');

        $specificationId = (string) Str::uuid();

        DB::table('product_specifications')->insert([
            'id' => $specificationId,
            'product_revision_id' => $revisionA->id,
            'specification_code' => 'SPEC-INT-001',
            'title' => 'Test Product Specification',
        ]);

        $this->expectException(QueryException::class);

        DB::table('inspection_plans')->insert([
            'id' => (string) Str::uuid(),
            'plan_number' => 'TEST-INT-004',
            'title' => 'Invalid specification-revision combination',
            'product_revision_id' => $revisionB->id,
            'product_specification_id' => $specificationId,
        ]);
    }
}