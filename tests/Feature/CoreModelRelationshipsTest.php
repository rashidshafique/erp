<?php

namespace Tests\Feature;

use App\Models\ManufacturingMethod;
use App\Models\Operation;
use App\Models\ProcessRoute;
use App\Models\Product;
use App\Models\ProductRevision;
use App\Models\WorkCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreModelRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_has_revisions(): void
    {
        $product = Product::create([
            'code' => 'TEST-001',
            'name' => 'Test Casting',
        ]);

        $revision = ProductRevision::create([
            'product_id' => $product->id,
            'revision' => 'A',
        ]);

        $this->assertTrue(
            $product->revisions()->whereKey($revision->id)->exists()
        );

        $this->assertTrue(
            $revision->product->is($product)
        );
    }

    public function test_revision_has_manufacturing_methods(): void
    {
        $product = Product::create([
            'code' => 'TEST-002',
            'name' => 'Test Pump Housing',
        ]);

        $revision = ProductRevision::create([
            'product_id' => $product->id,
            'revision' => 'A',
        ]);

        $method = ManufacturingMethod::create([
            'product_revision_id' => $revision->id,
            'code' => 'GREEN-SAND',
            'name' => 'Greensand Casting',
        ]);

        $this->assertTrue(
            $revision->manufacturingMethods()
                ->whereKey($method->id)
                ->exists()
        );

        $this->assertTrue(
            $method->productRevision->is($revision)
        );
    }

    public function test_manufacturing_method_has_routes_and_routes_have_ordered_operations(): void
    {
        $product = Product::create([
            'code' => 'TEST-003',
            'name' => 'Test Valve Body',
        ]);

        $revision = ProductRevision::create([
            'product_id' => $product->id,
            'revision' => 'A',
        ]);

        $method = ManufacturingMethod::create([
            'product_revision_id' => $revision->id,
            'code' => 'METHOD-1',
            'name' => 'Standard Casting',
        ]);

        $route = ProcessRoute::create([
            'manufacturing_method_id' => $method->id,
            'code' => 'ROUTE-1',
            'name' => 'Standard Production Route',
        ]);

        $workCenter = WorkCenter::create([
            'code' => 'MOULD-01',
            'name' => 'Moulding Section',
            'work_center_type' => 'MOULDING',
        ]);

        $laterOperation = Operation::create([
            'process_route_id' => $route->id,
            'work_center_id' => $workCenter->id,
            'sequence' => 20,
            'code' => 'POUR',
            'name' => 'Pour Mould',
            'operation_type' => 'POURING',
        ]);

        $firstOperation = Operation::create([
            'process_route_id' => $route->id,
            'work_center_id' => $workCenter->id,
            'sequence' => 10,
            'code' => 'MOULD',
            'name' => 'Prepare Mould',
            'operation_type' => 'MOULDING',
        ]);

        $this->assertTrue(
            $method->processRoutes()->whereKey($route->id)->exists()
        );

        $this->assertTrue(
            $route->manufacturingMethod->is($method)
        );

        $this->assertSame(
            [$firstOperation->id, $laterOperation->id],
            $route->operations->pluck('id')->all()
        );

        $this->assertTrue(
            $firstOperation->workCenter->is($workCenter)
        );

        $this->assertTrue(
            $workCenter->operations()->whereKey($firstOperation->id)->exists()
        );
    }
}