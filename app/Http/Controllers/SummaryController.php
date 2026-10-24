<?php

namespace App\Http\Controllers;

use App\Services\Ledger;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SummaryController extends Controller
{
    public function __invoke(Request $request, Ledger $ledger): Response
    {
        $year = max(2000, min(2100, $request->integer('year', CarbonImmutable::today()->year)));

        return Inertia::render('Summary/Index', $ledger->year($year));
    }
}
