<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    public function index(): View
    {
        $now = Carbon::now();
        $startOfYear = $now->copy()->startOfYear()->toDateString();
        $endOfYear = $now->copy()->endOfYear()->toDateString();
        $startOfMonth = $now->copy()->startOfMonth()->toDateString();
        $endOfMonth = $now->copy()->endOfMonth()->toDateString();

        // 1. Batch calculate balances for all accounts in 1 single query
        $balances = $this->reportService->getBatchAccountBalances();

        // Total Cash & Bank
        $cashBankAccounts = Account::where('type', 'asset')
            ->where(function ($q) {
                $q->where('code', 'LIKE', '10%')
                    ->orWhere('name', 'LIKE', '%Cash%')
                    ->orWhere('name', 'LIKE', '%Bank%');
            })
            ->get();
        $totalCashBank = $cashBankAccounts->sum(fn ($a) => $balances[$a->id] ?? 0.0);

        // Total Accounts Receivable
        $arAccounts = Account::where('type', 'asset')
            ->where(function ($q) {
                $q->where('code', 'LIKE', '12%')
                    ->orWhere('name', 'LIKE', '%Receivable%');
            })
            ->get();
        $totalAR = $arAccounts->sum(fn ($a) => $balances[$a->id] ?? 0.0);

        // Total Accounts Payable
        $apAccounts = Account::where('type', 'liability')
            ->where(function ($q) {
                $q->where('code', 'LIKE', '20%')
                    ->orWhere('name', 'LIKE', '%Payable%');
            })
            ->get();
        $totalAP = $apAccounts->sum(fn ($a) => $balances[$a->id] ?? 0.0);

        // 2. Current Month Net Income & YTD Net Income (each in 1 single grouped query)
        $monthlyIncomeData = $this->reportService->getIncomeStatement($startOfMonth, $endOfMonth);
        $ytdIncomeData = $this->reportService->getIncomeStatement($startOfYear, $endOfYear);

        $monthRevenue = $monthlyIncomeData['total_revenue'];
        $monthNetProfit = $monthlyIncomeData['net_income'];
        $ytdRevenue = $ytdIncomeData['total_revenue'];
        $ytdNetProfit = $ytdIncomeData['net_income'];

        // 3. Counts & Alerts
        $draftJournalsCount = JournalEntry::where('status', 'draft')->count();
        $unpaidInvoicesCount = Invoice::whereIn('status', ['sent', 'partially_paid', 'overdue'])->count();
        $unpaidBillsCount = Bill::whereIn('status', ['approved', 'partially_paid', 'overdue'])->count();

        // 4. Recent activities
        $recentJournals = JournalEntry::with('creator')->latest('date')->latest('id')->take(5)->get();
        $recentInvoices = Invoice::with('customer')->latest('issue_date')->take(5)->get();
        $recentBills = Bill::with('vendor')->latest('bill_date')->take(5)->get();

        // 5. Monthly Revenue & Expense trend for past 6 months in 1 single grouped query
        $trends = $this->reportService->getMonthlyTrends(6);
        $trendMonths = $trends['months'];
        $trendRevenues = $trends['revenues'];
        $trendExpenses = $trends['expenses'];

        return view('dashboard.index', compact(
            'totalCashBank',
            'totalAR',
            'totalAP',
            'monthRevenue',
            'monthNetProfit',
            'ytdRevenue',
            'ytdNetProfit',
            'draftJournalsCount',
            'unpaidInvoicesCount',
            'unpaidBillsCount',
            'recentJournals',
            'recentInvoices',
            'recentBills',
            'trendMonths',
            'trendRevenues',
            'trendExpenses'
        ));
    }
}
