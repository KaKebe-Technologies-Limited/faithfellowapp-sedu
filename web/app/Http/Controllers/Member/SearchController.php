<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\SearchService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request, SearchService $search): View
    {
        $q = trim((string) $request->query('q', ''));

        return view('member.search', [
            'q' => $q,
            'results' => mb_strlen($q) >= 2 ? $search->forMember($request->user(), $q) : [],
        ]);
    }
}
