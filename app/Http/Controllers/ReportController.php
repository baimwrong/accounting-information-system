<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    public function trialBalance(Request $request): View
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date') ?: Carbon::now()->toDateString();

        $data = $this->reportService->getTrialBalance($startDate, $endDate);

        return view('reports.trial_balance', $data);
    }

    public function balanceSheet(Request $request): View
    {
        $asOfDate = $request->input('as_of_date') ?: Carbon::now()->toDateString();

        $data = $this->reportService->getBalanceSheet($asOfDate);

        return view('reports.balance_sheet', $data);
    }

    public function incomeStatement(Request $request): View
    {
        $startDate = $request->input('start_date') ?: Carbon::now()->startOfYear()->toDateString();
        $endDate = $request->input('end_date') ?: Carbon::now()->toDateString();

        $data = $this->reportService->getIncomeStatement($startDate, $endDate);

        return view('reports.income_statement', $data);
    }

    public function generalLedger(Request $request): View
    {
        $accounts = Account::orderBy('code')->get();
        $selectedAccountId = $request->input('account_id', $accounts->first()?->id);
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $ledgerData = null;
        if ($selectedAccountId) {
            $ledgerData = $this->reportService->getGeneralLedgerAccount((int) $selectedAccountId, $startDate, $endDate);
        }

        return view('reports.general_ledger', [
            'accounts' => $accounts,
            'selectedAccountId' => $selectedAccountId,
            'ledgerData' => $ledgerData,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }
}
