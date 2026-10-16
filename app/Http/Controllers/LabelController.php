<?php

namespace App\Http\Controllers;

use App\Support\CodedRecords;
use Illuminate\Support\Facades\DB;

class LabelController extends Controller
{
    public function show(string $type, string $id)
    {
        abort_unless(isset(CodedRecords::TYPES[$type]), 404);
        [$table, $codeCol, $label, , $nameCol] = CodedRecords::TYPES[$type];
        $row = DB::table($table)->where('id', $id)->first();
        abort_unless($row, 404);

        // Tooling gets its mark on first print if it has none yet.
        $mark = null;
        if (in_array($type, CodedRecords::TOOLS, true)) {
            $class = (new \ReflectionClass(\App\Support\AttachableEntities::class))->getConstant('MAP')[$type][1];
            $mark = $class::find($id)->ensureMark();
        }

        return view('labels.show', [
            'type' => $type, 'id' => $id, 'kind' => $label, 'code' => (string) $row->{$codeCol},
            'name' => $nameCol ? $row->{$nameCol} : null, 'mark' => $mark,
            'sub' => $this->sub($type, $row),
        ]);
    }

    private function sub(string $type, object $row): ?string
    {
        if ($type === 'casting_lot') {
            $p = DB::table('products')->where('id', $row->product_id ?? null)->first();

            return $p ? $p->code.' · '.$p->name : null;
        }

        return null;
    }
}
