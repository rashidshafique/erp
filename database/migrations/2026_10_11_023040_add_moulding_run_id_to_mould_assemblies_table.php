
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mould_assemblies', function (Blueprint $table) {
            $table->foreignUuid('moulding_run_id')
                ->nullable()
                ->after('mould_configuration_id')
                ->constrained('moulding_runs')
                ->restrictOnDelete();

            $table->index(
                ['moulding_run_id', 'status'],
                'mould_assemblies_run_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('mould_assemblies', function (Blueprint $table) {
            $table->dropIndex('mould_assemblies_run_status_index');
            $table->dropConstrainedForeignId('moulding_run_id');
        });
    }
};
