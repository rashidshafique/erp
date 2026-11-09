<?php

use App\Models\AppSetting;
use App\Services\Codes\CodeGenerator;
use App\Support\DocumentNumbers;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BEFORE = 'codes.before_renumber';

    /**
     * Master codes become two letters and five digits from 10000 (CU-10000 customers, PR- products, PA- patterns, SU- suppliers,
     * EM- employees…), in the order the records were made, typed codes included. Codes made from them follow: pattern revisions
     * (PA-10000-R0), control plans and process routes named after a product, and the employee code on payslips. Old codes stay
     * in number_history, so search, scanning, file imports and the attendance clock still find the record by them.
     */
    public function up(): void
    {
        $kinds = $this->kinds();
        AppSetting::setValue(self::BEFORE, json_encode(['digits' => CodeGenerator::setting('code_digits', '5'), 'style' => CodeGenerator::setting('code_style', 'BOTH'),
            'prefixes' => collect($kinds)->mapWithKeys(fn ($v, $k) => [$k => AppSetting::getValue('codes.prefix.'.$k)])->all()]), 'Code settings before renumbering');
        AppSetting::setValue('codes.code_digits', '5', 'Digits in item codes');
        AppSetting::setValue('codes.code_style', 'BOTH', 'Item code style');

        $pairs = [];
        foreach ($kinds as $kind => [$table, $prefix]) {
            AppSetting::setValue('codes.prefix.'.$kind, $prefix, 'Code prefix');
            $pairs[$kind] = DocumentNumbers::renumberRows($table, 'code', $prefix, 'created_at');
            DB::table('code_sequences')->updateOrInsert(['key' => "code:{$kind}"], ['next_value' => DocumentNumbers::START + count($pairs[$kind])]);
        }
        $this->derived($pairs['pattern'] ?? [], $pairs['product'] ?? [], $pairs['employee'] ?? []);
        DocumentNumbers::rewriteNotices(array_merge(...array_values($pairs ?: [[]])));
    }

    public function down(): void
    {
        $kinds = $this->kinds();
        $flip = fn (string $table) => DB::table('number_history')->where('table_name', $table)->where('column_name', 'code')->pluck('old_number', 'new_number')->all();
        $this->derived($flip('patterns'), $flip('products'), $flip('employees'), true);
        $all = [];
        foreach ($kinds as $kind => [$table]) {
            $all += $flip($table);
            DocumentNumbers::restore($table, 'code');
            DB::table('code_sequences')->where('key', "code:{$kind}")->delete();
        }
        DocumentNumbers::rewriteNotices($all);
        $before = json_decode((string) AppSetting::getValue(self::BEFORE, '{}'), true) ?: [];
        AppSetting::setValue('codes.code_digits', (string) ($before['digits'] ?? '5'), 'Digits in item codes');
        AppSetting::setValue('codes.code_style', (string) ($before['style'] ?? 'BOTH'), 'Item code style');
        foreach ($before['prefixes'] ?? [] as $kind => $prefix) {
            AppSetting::setValue('codes.prefix.'.$kind, $prefix, 'Code prefix');
        }
    }

    /** @return array<string, array{0: string, 1: string}> kind => [table, new prefix], for coded masters whose table exists */
    private function kinds(): array
    {
        $out = [];
        foreach (CodeGenerator::kinds() as $kind => [$table, $prefix]) {
            if (! isset(DocumentNumbers::TYPES[$kind]) && Schema::hasTable($table) && Schema::hasColumn($table, 'code')) {
                $out[$kind] = [$table, $prefix];
            }
        }

        return $out;
    }

    /**
     * Codes built from a master's code.
     *
     * @param  array<string, string>  $patterns  old => new pattern codes
     * @param  array<string, string>  $products  old => new product codes
     * @param  array<string, string>  $employees  old => new employee codes
     */
    private function derived(array $patterns, array $products, array $employees, bool $back = false): void
    {
        if ($patterns && Schema::hasTable('pattern_revisions')) {
            // The patterns table holds the codes being moved to on the way up, and the ones being moved from on the way back.
            $codes = DB::table('patterns')->pluck('code', 'id');
            $fromNew = array_flip($patterns);
            $revised = [];
            foreach (DB::table('pattern_revisions')->whereNotNull('code')->get(['id', 'pattern_id', 'code']) as $r) {
                $current = $codes[$r->pattern_id] ?? null;
                [$from, $to] = $back ? [$current, $patterns[$current] ?? null] : [$fromNew[$current] ?? null, $current];
                if ($from !== null && $to !== null && str_starts_with($r->code, $from)) {
                    DB::table('pattern_revisions')->where('id', $r->id)->update(['code' => '~'.substr(md5((string) $r->id), 0, 14)]);
                    $revised[$r->id] = $to.substr($r->code, strlen($from));
                }
            }
            foreach ($revised as $id => $code) {
                DB::table('pattern_revisions')->where('id', $id)->update(['code' => $code]);
            }
        }
        foreach ([['process_control_plans', ['plan_number', 'title']], ['process_routes', ['code', 'name']]] as [$table, $columns]) {
            if (! $products || ! Schema::hasTable($table)) {
                continue;
            }
            foreach (DB::table($table)->get(array_merge(['id'], $columns)) as $row) {
                $set = [];
                foreach ($columns as $c) {
                    if ($row->{$c} !== null && ($v = strtr($row->{$c}, $products)) !== $row->{$c}) {
                        $set[$c] = $v;
                    }
                }
                if ($set) {
                    DB::table($table)->where('id', $row->id)->update($set);
                }
            }
        }
        if ($employees && Schema::hasTable('payslips')) {
            foreach (array_chunk(array_keys($employees), 500) as $olds) {
                foreach (DB::table('payslips')->whereIn('employee_code', $olds)->get(['id', 'employee_code']) as $s) {
                    DB::table('payslips')->where('id', $s->id)->update(['employee_code' => $employees[$s->employee_code]]);
                }
            }
        }
    }
};
