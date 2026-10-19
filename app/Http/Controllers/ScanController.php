<?php

namespace App\Http\Controllers;

use App\Support\CodedRecords;
use App\Support\GlobalSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ScanController extends Controller
{
    /** Enter in the search box: open the record when the match is clear, otherwise list the matches. */
    public function find(Request $request): View|RedirectResponse
    {
        $code = trim((string) $request->query('code', ''));
        $type = $this->type($request);
        if ($code === '') {
            return view('scan', ['type' => $type]);
        }
        if ($type === null && ($hit = CodedRecords::find($code))) {
            return redirect(CodedRecords::url($hit[0], $hit[1]));
        }
        $results = GlobalSearch::search($code, $type, Auth::user(), 50);
        if ($url = GlobalSearch::clearWinner($results)) {
            return redirect($url);
        }

        return view('scan', ['code' => $code, 'type' => $type, 'results' => $results, 'missing' => $results === []]);
    }

    /** Matches shown under the search box while typing. */
    public function suggest(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $type = $this->type($request);
        $offer = $type === null && ! str_contains($query, ' ') ? GlobalSearch::typeForWord($query) : null;

        return response()->json([
            'results' => GlobalSearch::search($query, $type, Auth::user()),
            'offer' => $offer && isset(GlobalSearch::typesFor(Auth::user())[$offer]) ? ['type' => $offer, 'label' => GlobalSearch::types()[$offer]['label']] : null,
        ]);
    }

    private function type(Request $request): ?string
    {
        $type = (string) $request->query('type', '');

        return isset(GlobalSearch::typesFor(Auth::user())[$type]) ? $type : null;
    }
}
