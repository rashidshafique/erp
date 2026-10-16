<?php

namespace App\Http\Controllers;

use App\Support\CodedRecords;
use Illuminate\Http\Request;

class ScanController extends Controller
{
    public function find(Request $request)
    {
        $code = trim((string) $request->query('code', ''));
        if ($code === '') {
            return view('scan');
        }
        $hit = CodedRecords::find($code);
        if (! $hit) {
            return view('scan', ['code' => $code, 'missing' => true]);
        }

        return redirect(CodedRecords::url($hit[0], $hit[1]));
    }
}
