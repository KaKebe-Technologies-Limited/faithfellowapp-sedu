<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Services\SearchService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request, Church $church, SearchService $search): View
    {
        $q = trim((string) $request->query('q', ''));

        return view('church-admin.search', [
            'church' => $church,
            'q' => $q,
            'results' => mb_strlen($q) >= 2 ? $search->forChurch($church, $q) : [],
        ]);
    }
}
