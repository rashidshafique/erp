<?php

use App\Domains\Ledger\Services\DefaultChart;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Double-entry books: a chart of accounts as a tree, and journals whose lines always balance. */
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('type', 20);
            $table->uuid('parent_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('description', 300)->nullable();
            $table->timestamps();

            $table->index('parent_id');
        });
        Schema::table('ledger_accounts', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('ledger_accounts')->restrictOnDelete();
        });

        Schema::create('ledger_journals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 40)->unique();
            $table->date('journal_date');
            $table->string('kind', 20)->default('POST');
            $table->string('source_type', 30)->nullable();
            $table->uuid('source_id')->nullable();
            $table->uuid('reverses_id')->nullable();
            $table->string('memo', 300)->nullable();
            $table->decimal('total', 14, 2)->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'kind']);
            $table->index('journal_date');
        });
        Schema::table('ledger_journals', function (Blueprint $table) {
            $table->foreign('reverses_id')->references('id')->on('ledger_journals')->restrictOnDelete();
        });

        Schema::create('ledger_journal_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('journal_id')->constrained('ledger_journals')->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('party_type', 20)->nullable();
            $table->uuid('party_id')->nullable();
            $table->string('description', 300)->nullable();
            $table->timestamps();

            $table->index(['account_id', 'journal_id']);
            $table->index(['party_type', 'party_id']);
        });

        DefaultChart::install();

        if (Schema::hasTable('roles') && ($role = DB::table('roles')->where('code', 'ACCOUNTS')->value('id'))
            && ! DB::table('role_permissions')->where('role_id', $role)->where('permission', 'ledger.manage')->exists()) {
            DB::table('role_permissions')->insert(['role_id' => $role, 'permission' => 'ledger.manage']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_journal_lines');
        Schema::dropIfExists('ledger_journals');
        Schema::dropIfExists('ledger_accounts');
        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')->where('permission', 'ledger.manage')->delete();
        }
    }
};
