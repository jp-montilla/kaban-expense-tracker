<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\Ledger;
use App\Services\MonthSheet;
use App\Support\Period;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, Ledger $ledger, MonthSheet $sheet): Response
    {
        $period = Period::fromRequest($request);
        $overview = $ledger->overview($period->year, $period->month, $period->half, $period);
        $dues = $sheet->dues($period);
        $funding = $sheet->funding(
            $period,
            $overview['figures']['first_cutoff'],
            $overview['figures']['second_cutoff'],
        );
        $tiles = $sheet->payTiles($funding, $period->half);
        $overview['figures']['view_bills'] = $tiles['bills'];
        $overview['figures']['view_credit'] = $tiles['credit'];
        $overview['figures']['view_loan'] = $tiles['loan'];
        $overview['figures']['saved'] = $tiles['saved'];
        $overview['figures']['approx_savings'] = $tiles['leftover'];

        return Inertia::render('Dashboard', [
            ...$overview,
            'funding' => $funding,
            'cash_bills' => $sheet->cashDueRows($period),
            'dues' => $dues,
            'debts' => $sheet->loans($period),
            'logCategories' => Category::query()
                ->where('is_active', true)
                ->where('kind', 'variable')
                ->where('slug', 'daily')
                ->orderBy('sort_order')
                ->get(['id', 'name', 'slug', 'kind', 'color', 'monthly_plan'])
                ->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'kind' => $category->kind,
                    'color' => $category->color,
                    'monthly_plan' => (float) $category->monthly_plan,
                ])
                ->all(),
        ]);
    }
}
