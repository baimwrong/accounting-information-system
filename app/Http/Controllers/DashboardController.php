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

        // 1. Total Cash & Bank
        $cashBankAccounts = Account::where('type', 'asset')
            ->where(function ($q) {
                $q->where('code', 'LIKE', '10%')
                    ->orWhere('name', 'LIKE', '%Cash%')
                    ->orWhere('name', 'LIKE', '%Bank%');
            })
            ->get();
        $totalCashBank = $cashBankAccounts->sum(fn ($a) => $a->balance);

        // 2. Total Accounts Receivable
        $arAccounts = Account::where('type', 'asset')
            ->where(function ($q) {
                $q->where('code', 'LIKE', '12%')
                    ->orWhere('name', 'LIKE', '%Receivable%');
            })
            ->get();
        $totalAR = $arAccounts->sum(fn ($a) => $a->balance);

        // 3. Total Accounts Payable
        $apAccounts = Account::where('type', 'liability')
            ->where(function ($q) {
                $q->where('code', 'LIKE', '20%')
                    ->orWhere('name', 'LIKE', '%Payable%');
            })
            ->get();
        $totalAP = $apAccounts->sum(fn ($a) => $a->balance);

        // 4. Current Month Net Income & YTD Net Income
        $monthlyIncomeData = $this->reportService->getIncomeStatement($startOfMonth, $endOfMonth);
        $ytdIncomeData = $this->reportService->getIncomeStatement($startOfYear, $endOfYear);

        $monthRevenue = $monthlyIncomeData['total_revenue'];
        $monthNetProfit = $monthlyIncomeData['net_income'];
        $ytdRevenue = $ytdIncomeData['total_revenue'];
        $ytdNetProfit = $ytdIncomeData['net_income'];

        // 5. Counts & Alerts
        $draftJournalsCount = JournalEntry::where('status', 'draft')->count();
        $unpaidInvoicesCount = Invoice::whereIn('status', ['sent', 'partially_paid', 'overdue'])->count();
        $unpaidBillsCount = Bill::whereIn('status', ['approved', 'partially_paid', 'overdue'])->count();

        // 6. Recent activities
        $recentJournals = JournalEntry::with('creator')->latest('date')->latest('id')->take(5)->get();
        $recentInvoices = Invoice::with('customer')->latest('issue_date')->take(5)->get();
        $recentBills = Bill::with('vendor')->latest('bill_date')->take(5)->get();

        // 7. Monthly Revenue & Expense trend for past 6 months
        $trendMonths = [];
        $trendRevenues = [];
        $trendExpenses = [];

        for ($i = 5; $i >= 0; $i--) {
            $monthDate = Carbon::now()->subMonths($i);
            $mStart = $monthDate->copy()->startOfMonth()->toDateString();
            $mEnd = $monthDate->copy()->endOfMonth()->toDateString();

            $incomeStmt = $this->reportService->getIncomeStatement($mStart, $mEnd);
            $trendMonths[] = $monthDate->format('M Y');
            $trendRevenues[] = round($incomeStmt['total_revenue'], 2);
            $trendExpenses[] = round($incomeStmt['total_cogs'] + $incomeStmt['total_operating_expenses'], 2);
        }

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
