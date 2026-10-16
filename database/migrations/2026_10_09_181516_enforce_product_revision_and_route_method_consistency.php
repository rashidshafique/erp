
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Composite unique keys allow PostgreSQL to verify that a
         * product revision belongs to the selected product.
         */
        Schema::table('product_revisions', function (Blueprint $table) {
            $table->unique(
                ['id', 'product_id'],
                'product_revisions_id_product_id_unique'
            );
        });

        Schema::table('process_routes', function (Blueprint $table) {
            $table->unique(
                ['id', 'manufacturing_method_id'],
                'process_routes_id_method_id_unique'
            );
        });

        /*
         * Product/revision consistency.
         */
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->foreign(
                ['product_revision_id', 'product_id'],
                'sales_order_lines_revision_product_fk'
            )->references(['id', 'product_id'])
                ->on('product_revisions')
                ->restrictOnDelete();
        });

        Schema::table('production_orders', function (Blueprint $table) {
            $table->foreign(
                ['product_revision_id', 'product_id'],
                'production_orders_revision_product_fk'
            )->references(['id', 'product_id'])
                ->on('product_revisions')
                ->restrictOnDelete();
        });

        Schema::table('casting_lots', function (Blueprint $table) {
            $table->foreign(
                ['product_revision_id', 'product_id'],
                'casting_lots_revision_product_fk'
            )->references(['id', 'product_id'])
                ->on('product_revisions')
                ->restrictOnDelete();
        });

        /*
         * Route/method consistency.
         */
        Schema::table('production_orders', function (Blueprint $table) {
            $table->foreign(
                ['process_route_id', 'manufacturing_method_id'],
                'production_orders_route_method_fk'
            )->references(['id', 'manufacturing_method_id'])
                ->on('process_routes')
                ->restrictOnDelete();
        });

        Schema::table('process_control_plans', function (Blueprint $table) {
            $table->foreign(
                ['process_route_id', 'manufacturing_method_id'],
                'process_control_plans_route_method_fk'
            )->references(['id', 'manufacturing_method_id'])
                ->on('process_routes')
                ->restrictOnDelete();
        });

        /*
         * A route cannot be specified without its manufacturing method.
         * The composite FK alone would not reject this when the method
         * column is NULL.
         */
        DB::statement("
            ALTER TABLE production_orders
            ADD CONSTRAINT production_orders_route_requires_method_check
            CHECK (
                process_route_id IS NULL
                OR manufacturing_method_id IS NOT NULL
            )
        ");

        DB::statement("
            ALTER TABLE process_control_plans
            ADD CONSTRAINT process_control_plans_route_requires_method_check
            CHECK (
                process_route_id IS NULL
                OR manufacturing_method_id IS NOT NULL
            )
        ");
    }

    public function down(): void
    {
        Schema::table('process_control_plans', function (Blueprint $table) {
            $table->dropForeign(
                'process_control_plans_route_method_fk'
            );
        });

        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropForeign(
                'production_orders_route_method_fk'
            );

            $table->dropForeign(
                'production_orders_revision_product_fk'
            );
        });

        Schema::table('casting_lots', function (Blueprint $table) {
            $table->dropForeign(
                'casting_lots_revision_product_fk'
            );
        });

        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->dropForeign(
                'sales_order_lines_revision_product_fk'
            );
        });

        DB::statement("
            ALTER TABLE production_orders
            DROP CONSTRAINT production_orders_route_requires_method_check
        ");

        DB::statement("
            ALTER TABLE process_control_plans
            DROP CONSTRAINT process_control_plans_route_requires_method_check
        ");

        Schema::table('process_routes', function (Blueprint $table) {
            $table->dropUnique('process_routes_id_method_id_unique');
        });

        Schema::table('product_revisions', function (Blueprint $table) {
            $table->dropUnique('product_revisions_id_product_id_unique');
        });
    }
};