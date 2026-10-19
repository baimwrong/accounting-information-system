<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function arAging(Request $request): View
    {
        $asOfDate = $request->input('as_of_date') ?: Carbon::now()->toDateString();
        $data = $this->reportService->getArAgingReport($asOfDate);

        return view('reports.ar_aging', $data);
    }

    public function apAging(Request $request): View
    {
        $asOfDate = $request->input('as_of_date') ?: Carbon::now()->toDateString();
        $data = $this->reportService->getApAgingReport($asOfDate);

        return view('reports.ap_aging', $data);
    }

    public function cashFlow(Request $request): View
    {
        $startDate = $request->input('start_date') ?: Carbon::now()->startOfYear()->toDateString();
        $endDate = $request->input('end_date') ?: Carbon::now()->toDateString();

        $data = $this->reportService->getCashFlowStatement($startDate, $endDate);

        return view('reports.cash_flow', $data);
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date') ?: Carbon::now()->toDateString();
        $asOfDate = $request->input('as_of_date') ?: Carbon::now()->toDateString();

        $data = match ($type) {
            'trial_balance' => $this->reportService->getTrialBalance($startDate, $endDate),
            'balance_sheet' => $this->reportService->getBalanceSheet($asOfDate),
            'income_statement' => $this->reportService->getIncomeStatement(
                $startDate ?: Carbon::now()->startOfYear()->toDateString(),
                $endDate
            ),
            'ar_aging' => $this->reportService->getArAgingReport($asOfDate),
            'ap_aging' => $this->reportService->getApAgingReport($asOfDate),
            'cash_flow' => $this->reportService->getCashFlowStatement(
                $startDate ?: Carbon::now()->startOfYear()->toDateString(),
                $endDate
            ),
            default => abort(404, 'Invalid report type for export.'),
        };

        return $this->reportService->exportToCsv($type, $data);
    }
}
