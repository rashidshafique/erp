
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Allow inspection plans to reference a specification only
        // when its product revision matches the plan revision.
        Schema::table('product_specifications', function (Blueprint $table) {
            $table->unique(
                ['id', 'product_revision_id'],
                'product_specifications_id_revision_unique'
            );
        });

        DB::statement('
            ALTER TABLE inspection_plans
            ADD CONSTRAINT inspection_plans_spec_revision_fk
            FOREIGN KEY (product_specification_id, product_revision_id)
            REFERENCES product_specifications (id, product_revision_id)
        ');

        // Support a composite FK for route-specific operations.
        Schema::table('operations', function (Blueprint $table) {
            $table->unique(
                ['id', 'process_route_id'],
                'operations_id_route_unique'
            );
        });

        Schema::table('process_control_plans', function (Blueprint $table) {
            $table->foreign(
                ['operation_id', 'process_route_id'],
                'process_control_plans_operation_route_fk'
            )
                ->references(['id', 'process_route_id'])
                ->on('operations')
                ->restrictOnDelete();
        });

        DB::statement('
            ALTER TABLE process_control_plans
            ADD CONSTRAINT process_control_plans_operation_requires_route_check
            CHECK (operation_id IS NULL OR process_route_id IS NOT NULL)
        ');
    }

    public function down(): void
    {
        DB::statement('
            ALTER TABLE process_control_plans
            DROP CONSTRAINT IF EXISTS
            process_control_plans_operation_requires_route_check
        ');

        Schema::table('process_control_plans', function (Blueprint $table) {
            $table->dropForeign(
                'process_control_plans_operation_route_fk'
            );
        });

        DB::statement('
            ALTER TABLE inspection_plans
            DROP CONSTRAINT IF EXISTS inspection_plans_spec_revision_fk
        ');

        Schema::table('operations', function (Blueprint $table) {
            $table->dropUnique('operations_id_route_unique');
        });

        Schema::table('product_specifications', function (Blueprint $table) {
            $table->dropUnique(
                'product_specifications_id_revision_unique'
            );
        });
    }
};