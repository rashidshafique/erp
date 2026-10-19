<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('name', 200);
            $table->string('short_name', 60)->nullable();
            $table->string('contact_name', 150)->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('email', 160)->nullable();
            $table->text('billing_address')->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('tax_number', 60)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('name');
        });

        Schema::table('customers', fn (Blueprint $table) => $table->foreignUuid('party_id')->nullable()->constrained('parties')->restrictOnDelete());
        Schema::table('suppliers', fn (Blueprint $table) => $table->foreignUuid('party_id')->nullable()->constrained('parties')->restrictOnDelete());

        $n = 0;
        $code = function () use (&$n) {
            return 'BP'.str_pad((string) ++$n, 5, '0', STR_PAD_LEFT);
        };
        foreach (DB::table('customers')->orderBy('created_at')->get() as $c) {
            $id = (string) Str::uuid();
            DB::table('parties')->insert([
                'id' => $id, 'code' => $code(), 'name' => $c->name, 'short_name' => $c->short_name, 'contact_name' => $c->contact_name, 'phone' => $c->phone,
                'email' => $c->email, 'billing_address' => $c->billing_address, 'shipping_address' => $c->shipping_address, 'tax_number' => $c->tax_number,
                'active' => $c->active, 'created_at' => $c->created_at, 'updated_at' => $c->updated_at, 'deleted_at' => $c->deleted_at,
            ]);
            DB::table('customers')->where('id', $c->id)->update(['party_id' => $id]);
        }
        foreach (DB::table('suppliers')->orderBy('created_at')->get() as $s) {
            $id = (string) Str::uuid();
            DB::table('parties')->insert([
                'id' => $id, 'code' => $code(), 'name' => $s->name, 'contact_name' => $s->contact_person, 'phone' => $s->phone, 'email' => $s->email,
                'billing_address' => $s->address, 'active' => ! in_array($s->approval_status, ['DISQUALIFIED'], true),
                'created_at' => $s->created_at, 'updated_at' => $s->updated_at, 'deleted_at' => $s->deleted_at,
            ]);
            DB::table('suppliers')->where('id', $s->id)->update(['party_id' => $id]);
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['name', 'short_name', 'contact_name', 'phone', 'email', 'billing_address', 'shipping_address', 'tax_number', 'active']);
        });
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['name', 'contact_person', 'phone', 'email', 'address']);
        });
        DB::statement('alter table customers alter column party_id set not null');
        DB::statement('alter table suppliers alter column party_id set not null');
        Schema::table('customers', fn (Blueprint $table) => $table->unique('party_id'));
        Schema::table('suppliers', fn (Blueprint $table) => $table->unique('party_id'));
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['party_id']);
            $table->string('name')->default('');
            $table->string('short_name')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('billing_address')->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('tax_number')->nullable();
            $table->boolean('active')->default(true);
        });
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropUnique(['party_id']);
            $table->string('name', 150)->default('');
            $table->string('contact_person', 150)->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('address')->nullable();
        });
        DB::statement('update customers set name = p.name, short_name = p.short_name, contact_name = p.contact_name, phone = p.phone, email = p.email, billing_address = p.billing_address, shipping_address = p.shipping_address, tax_number = p.tax_number, active = p.active from parties p where p.id = customers.party_id');
        DB::statement('update suppliers set name = p.name, contact_person = p.contact_name, phone = p.phone, email = p.email, address = p.billing_address from parties p where p.id = suppliers.party_id');
        Schema::table('customers', fn (Blueprint $table) => $table->dropConstrainedForeignId('party_id'));
        Schema::table('suppliers', fn (Blueprint $table) => $table->dropConstrainedForeignId('party_id'));
        Schema::dropIfExists('parties');
    }
};
