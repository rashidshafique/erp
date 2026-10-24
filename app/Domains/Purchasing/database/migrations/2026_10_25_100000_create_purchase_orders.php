<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('po_number', 40)->nullable()->unique();
            $table->string('status', 20)->default('DRAFT');
            $table->foreignUuid('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('payment_terms', 100)->nullable();
            $table->string('supplier_reference', 100)->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestampTz('issued_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->string('close_reason', 300)->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
            $table->index(['status', 'expected_date']);
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->foreignUuid('material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->string('description', 300);
            $table->decimal('ordered_qty', 14, 3);
            $table->decimal('received_qty', 14, 3)->default(0);
            $table->string('uom', 20)->nullable();
            $table->decimal('unit_price', 14, 4);
            $table->decimal('tax_percent', 7, 3)->default(0);
            $table->decimal('line_net', 14, 2);
            $table->decimal('line_tax', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::table('material_lots', function (Blueprint $table) {
            $table->uuid('purchase_order_line_id')->nullable()->index();
        });

        $now = now();
        if (Schema::hasTable('roles') && ! DB::table('roles')->where('code', 'BUYER')->exists()) {
            $id = DB::table('roles')->insertGetId(['code' => 'BUYER', 'name' => 'Buyer', 'is_system' => false, 'created_at' => $now, 'updated_at' => $now]);
            foreach (['purchasing.manage', 'attachments.manage'] as $p) {
                DB::table('role_permissions')->insert(['role_id' => $id, 'permission' => $p]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('material_lots', function (Blueprint $table) {
            $table->dropColumn('purchase_order_line_id');
        });
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
        if (Schema::hasTable('roles')) {
            $id = DB::table('roles')->where('code', 'BUYER')->value('id');
            if ($id) {
                DB::table('role_permissions')->where('role_id', $id)->delete();
                DB::table('roles')->where('id', $id)->delete();
            }
        }
    }
};
