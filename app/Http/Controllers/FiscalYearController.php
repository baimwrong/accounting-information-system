<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\FiscalYear;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FiscalYearController extends Controller
{
    public function index(): View
    {
        $fiscalYears = FiscalYear::with('closedByUser')->orderBy('start_date', 'desc')->paginate(15);

        return view('fiscal_years.index', compact('fiscalYears'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:fiscal_years,name'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ]);

        $fy = FiscalYear::create([
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => 'active',
        ]);

        AuditLog::record(
            $request->user(),
            'create_fiscal_year',
            "Opened fiscal period {$fy->name} ({$fy->start_date->format('Y-m-d')} to {$fy->end_date->format('Y-m-d')})",
            $fy
        );

        return redirect()->route('fiscal-years.index')->with('success', "Fiscal Year {$fy->name} created.");
    }

    public function close(Request $request, FiscalYear $fiscalYear): RedirectResponse
    {
        if ($fiscalYear->status === 'closed') {
            return back()->with('info', 'This fiscal period is already closed.');
        }

        $fiscalYear->update([
            'status' => 'closed',
            'closed_at' => Carbon::now(),
            'closed_by' => $request->user()->id,
        ]);

        AuditLog::record(
            $request->user(),
            'close_fiscal_year',
            "Closed and locked fiscal period {$fiscalYear->name}. Historic transactions are now read-only.",
            $fiscalYear
        );

        return redirect()->route('fiscal-years.index')->with('success', "Fiscal Year {$fiscalYear->name} closed and locked.");
    }
}
