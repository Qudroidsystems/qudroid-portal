<?php

namespace App\Http\Controllers;

use App\Services\Search\GlobalSearch;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request, GlobalSearch $search)
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);

        if ($request->expectsJson()) {
            return response()->json(['results' => $search->search($request->user(), $q, 4)->values()]);
        }

        $results = $search->search($request->user(), $q, 20);

        return view('search.index', ['q' => $q, 'groups' => $results->groupBy('category'), 'count' => $results->count()]);
    }
}
