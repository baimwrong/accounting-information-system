<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Invoice;
use App\Models\JournalEntryItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportService
{
    /**
     * Compute aggregated net balances for all accounts or a filtered subset in a single query.
     * Returns an associative map: [account_id => net_balance]
     *
     * @param  array<int>|null  $accountIds
     * @return array<int, float>
     */
    public function getBatchAccountBalances(?string $asOfDate = null, ?array $accountIds = null): array
    {
        $query = DB::table('journal_entry_items')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_items.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_items.account_id')
            ->where('journal_entries.status', 'posted');

        if ($asOfDate) {
            $query->where('journal_entries.date', '<=', $asOfDate);
        }

        if (! empty($accountIds)) {
            $query->whereIn('journal_entry_items.account_id', $accountIds);
        }

        $results = $query->groupBy('journal_entry_items.account_id', 'journal_entry_items.type', 'accounts.normal_balance')
            ->selectRaw('journal_entry_items.account_id, journal_entry_items.type, accounts.normal_balance, SUM(journal_entry_items.amount) as total')
            ->get();

        $totals = [];
        foreach ($results as $row) {
            $id = (int) $row->account_id;
            if (! isset($totals[$id])) {
                $totals[$id] = [
                    'debit' => 0.0,
                    'credit' => 0.0,
                    'normal_balance' => $row->normal_balance,
                ];
            }
            if ($row->type === 'debit') {
                $totals[$id]['debit'] = (float) $row->total;
            } elseif ($row->type === 'credit') {
                $totals[$id]['credit'] = (float) $row->total;
            }
        }

        $balances = [];
        foreach ($totals as $id => $data) {
            if ($data['normal_balance'] === 'debit') {
                $balances[$id] = round($data['debit'] - $data['credit'], 4);
            } else {
                $balances[$id] = round($data['credit'] - $data['debit'], 4);
            }
        }

        return $balances;
    }

    /**
     * Compute aggregated debit and credit sums per account within a date range in a single query.
     *
     * @param  array<int>|null  $accountIds
     * @return array<int, array{debit: float, credit: float}>
     */
    public function getBatchPeriodSums(string $startDate, string $endDate, ?array $accountIds = null): array
    {
        $query = DB::table('journal_entry_items')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_items.journal_entry_id')
            ->where('journal_entries.status', 'posted')
            ->whereBetween('journal_entries.date', [$startDate, $endDate]);

        if (! empty($accountIds)) {
            $query->whereIn('journal_entry_items.account_id', $accountIds);
        }

        $results = $query->groupBy('journal_entry_items.account_id', 'journal_entry_items.type')
            ->selectRaw('journal_entry_items.account_id, journal_entry_items.type, SUM(journal_entry_items.amount) as total')
            ->get();

        $sums = [];
        foreach ($results as $row) {
            $id = (int) $row->account_id;
            if (! isset($sums[$id])) {
                $sums[$id] = ['debit' => 0.0, 'credit' => 0.0];
            }
            $sums[$id][$row->type] = (float) $row->total;
        }

        return $sums;
    }

    /**
     * Generate Trial Balance without N+1 query overhead.
     */
    public function getTrialBalance(?string $startDate = null, ?string $endDate = null): array
    {
        $accounts = Account::orderBy('code')->get();

        $query = DB::table('journal_entry_items')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_items.journal_entry_id')
            ->where('journal_entries.status', 'posted');

        if ($startDate) {
            $query->where('journal_entries.date', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('journal_entries.date', '<=', $endDate);
        }

        $sums = $query->groupBy('journal_entry_items.account_id', 'journal_entry_items.type')
            ->selectRaw('journal_entry_items.account_id, journal_entry_items.type, SUM(journal_entry_items.amount) as total')
            ->get();

        $totalsByAccount = [];
        foreach ($sums as $row) {
            $aid = (int) $row->account_id;
            $totalsByAccount[$aid][$row->type] = (float) $row->total;
        }

        $rows = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($accounts as $account) {
            $debit = $totalsByAccount[$account->id]['debit'] ?? 0.0;
            $credit = $totalsByAccount[$account->id]['credit'] ?? 0.0;

            $net = $debit - $credit;
            $endingDebit = 0.0;
            $endingCredit = 0.0;

            if ($net > 0) {
                $endingDebit = $net;
            } elseif ($net < 0) {
                $endingCredit = abs($net);
            }

            $totalDebit += $endingDebit;
            $totalCredit += $endingCredit;

            $rows[] = [
                'account' => $account,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'normal_balance' => $account->normal_balance,
                'debit' => $debit,
                'credit' => $credit,
                'ending_debit' => $endingDebit,
                'ending_credit' => $endingCredit,
            ];
        }

        return [
            'rows' => $rows,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'is_balanced' => abs($totalDebit - $totalCredit) < 0.01,
            'difference' => abs($totalDebit - $totalCredit),
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    /**
     * Generate Balance Sheet as of given date using batch aggregated balances.
     */
    public function getBalanceSheet(?string $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?: Carbon::now()->toDateString();

        $allAccounts = Account::orderBy('code')->get();
        $balances = $this->getBatchAccountBalances($asOfDate);

        $assets = [];
        $totalAssets = 0.0;

        $liabilities = [];
        $totalLiabilities = 0.0;

        $equity = [];
        $totalEquityWithoutIncome = 0.0;

        $totalRevenue = 0.0;
        $totalExpense = 0.0;

        foreach ($allAccounts as $acc) {
            $bal = $balances[$acc->id] ?? 0.0;

            switch ($acc->type) {
                case 'asset':
                    $assets[] = ['account' => $acc, 'balance' => $bal];
                    $totalAssets += $bal;
                    break;

                case 'liability':
                    $liabilities[] = ['account' => $acc, 'balance' => $bal];
                    $totalLiabilities += $bal;
                    break;

                case 'equity':
                    $equity[] = ['account' => $acc, 'balance' => $bal];
                    $totalEquityWithoutIncome += $bal;
                    break;

                case 'revenue':
                    $totalRevenue += $bal;
                    break;

                case 'expense':
                    $totalExpense += $bal;
                    break;
            }
        }

        $currentYearEarnings = $totalRevenue - $totalExpense;
        $totalEquity = $totalEquityWithoutIncome + $currentYearEarnings;

        $isBalanced = abs($totalAssets - ($totalLiabilities + $totalEquity)) < 0.01;

        return [
            'as_of_date' => $asOfDate,
            'assets' => $assets,
            'total_assets' => $totalAssets,
            'liabilities' => $liabilities,
            'total_liabilities' => $totalLiabilities,
            'equity' => $equity,
            'current_year_earnings' => $currentYearEarnings,
            'total_equity' => $totalEquity,
            'total_liabilities_and_equity' => $totalLiabilities + $totalEquity,
            'is_balanced' => $isBalanced,
            'difference' => abs($totalAssets - ($totalLiabilities + $totalEquity)),
        ];
    }

    /**
     * Generate Income Statement (Profit & Loss) using batch period sums.
     */
    public function getIncomeStatement(string $startDate, string $endDate): array
    {
        $revenueAccounts = Account::where('type', 'revenue')->orderBy('code')->get();
        $expenseAccounts = Account::where('type', 'expense')->orderBy('code')->get();

        $periodSums = $this->getBatchPeriodSums($startDate, $endDate);

        $revenues = [];
        $totalRevenue = 0.0;
        foreach ($revenueAccounts as $acc) {
            $dr = $periodSums[$acc->id]['debit'] ?? 0.0;
            $cr = $periodSums[$acc->id]['credit'] ?? 0.0;
            $bal = $acc->normal_balance === 'debit' ? ($dr - $cr) : ($cr - $dr);

            $revenues[] = ['account' => $acc, 'balance' => $bal];
            $totalRevenue += $bal;
        }

        $cogs = [];
        $totalCogs = 0.0;
        $operatingExpenses = [];
        $totalOperatingExpenses = 0.0;

        foreach ($expenseAccounts as $acc) {
            $dr = $periodSums[$acc->id]['debit'] ?? 0.0;
            $cr = $periodSums[$acc->id]['credit'] ?? 0.0;
            $bal = $acc->normal_balance === 'debit' ? ($dr - $cr) : ($cr - $dr);

            if ($acc->sub_type === 'cost_of_goods_sold' || str_contains(strtolower($acc->name), 'cogs') || str_contains(strtolower($acc->name), 'cost of goods')) {
                $cogs[] = ['account' => $acc, 'balance' => $bal];
                $totalCogs += $bal;
            } else {
                $operatingExpenses[] = ['account' => $acc, 'balance' => $bal];
                $totalOperatingExpenses += $bal;
            }
        }

        $grossProfit = $totalRevenue - $totalCogs;
        $netIncome = $grossProfit - $totalOperatingExpenses;

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'revenues' => $revenues,
            'total_revenue' => $totalRevenue,
            'cogs' => $cogs,
            'total_cogs' => $totalCogs,
            'gross_profit' => $grossProfit,
            'operating_expenses' => $operatingExpenses,
            'total_operating_expenses' => $totalOperatingExpenses,
            'net_income' => $netIncome,
        ];
    }

    /**
     * General Ledger detail statement for an account.
     */
    public function getGeneralLedgerAccount(int $accountId, ?string $startDate = null, ?string $endDate = null): array
    {
        $account = Account::findOrFail($accountId);

        // Calculate opening balance before startDate in a single fast query
        $openingBalance = 0.0;
        if ($startDate) {
            $priorSums = DB::table('journal_entry_items')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_items.journal_entry_id')
                ->where('journal_entry_items.account_id', $account->id)
                ->where('journal_entries.status', 'posted')
                ->where('journal_entries.date', '<', $startDate)
                ->groupBy('journal_entry_items.type')
                ->selectRaw('journal_entry_items.type, SUM(journal_entry_items.amount) as total')
                ->pluck('total', 'type');

            $priorDebit = (float) ($priorSums['debit'] ?? 0.0);
            $priorCredit = (float) ($priorSums['credit'] ?? 0.0);

            $openingBalance = $account->normal_balance === 'debit'
                ? ($priorDebit - $priorCredit)
                : ($priorCredit - $priorDebit);
        }

        $itemsQuery = JournalEntryItem::with('journalEntry')
            ->where('account_id', $account->id)
            ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                $q->where('status', 'posted');
                if ($startDate) {
                    $q->where('date', '>=', $startDate);
                }
                if ($endDate) {
                    $q->where('date', '<=', $endDate);
                }
            })
            ->join('journal_entries', 'journal_entry_items.journal_entry_id', '=', 'journal_entries.id')
            ->orderBy('journal_entries.date', 'asc')
            ->orderBy('journal_entries.id', 'asc')
            ->select('journal_entry_items.*');

        $items = $itemsQuery->get();

        $running = $openingBalance;
        $lines = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($items as $item) {
            $dr = $item->type === 'debit' ? (float) $item->amount : 0.0;
            $cr = $item->type === 'credit' ? (float) $item->amount : 0.0;

            $totalDebit += $dr;
            $totalCredit += $cr;

            if ($account->normal_balance === 'debit') {
                $running += ($dr - $cr);
            } else {
                $running += ($cr - $dr);
            }

            $lines[] = [
                'date' => $item->journalEntry->date->format('Y-m-d'),
                'entry_number' => $item->journalEntry->entry_number,
                'journal_entry_id' => $item->journalEntry->id,
                'reference' => $item->journalEntry->reference,
                'description' => $item->memo ?: $item->journalEntry->description,
                'debit' => $dr,
                'credit' => $cr,
                'running_balance' => $running,
            ];
        }

        return [
            'account' => $account,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'opening_balance' => $openingBalance,
            'lines' => $lines,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'ending_balance' => $running,
        ];
    }

    /**
     * Fetch monthly revenue and expense trends for the past N months in a single query.
     */
    public function getMonthlyTrends(int $months = 6): array
    {
        $monthsList = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $m = Carbon::now()->subMonths($i);
            $key = $m->format('Y-m');
            $monthsList[$key] = [
                'label' => $m->format('M Y'),
                'revenue' => 0.0,
                'expense' => 0.0,
            ];
        }

        $earliest = Carbon::now()->subMonths($months - 1)->startOfMonth()->toDateString();
        $latest = Carbon::now()->endOfMonth()->toDateString();

        $rows = DB::table('journal_entry_items')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_items.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_items.account_id')
            ->where('journal_entries.status', 'posted')
            ->whereBetween('journal_entries.date', [$earliest, $latest])
            ->whereIn('accounts.type', ['revenue', 'expense'])
            ->selectRaw('SUBSTRING(journal_entries.date, 1, 7) as ym, accounts.type as acc_type, accounts.normal_balance, journal_entry_items.type as item_type, SUM(journal_entry_items.amount) as total')
            ->groupBy(DB::raw('SUBSTRING(journal_entries.date, 1, 7)'), 'accounts.type', 'accounts.normal_balance', 'journal_entry_items.type')
            ->get();

        $periodData = [];
        foreach ($rows as $row) {
            $ym = $row->ym;
            if (! isset($periodData[$ym])) {
                $periodData[$ym] = ['revenue' => 0.0, 'expense' => 0.0];
            }

            $amount = (float) $row->total;
            if ($row->acc_type === 'revenue') {
                $net = $row->item_type === 'credit' ? $amount : -$amount;
                $periodData[$ym]['revenue'] += $net;
            } elseif ($row->acc_type === 'expense') {
                $net = $row->item_type === 'debit' ? $amount : -$amount;
                $periodData[$ym]['expense'] += $net;
            }
        }

        $trendMonths = [];
        $trendRevenues = [];
        $trendExpenses = [];

        foreach ($monthsList as $key => $meta) {
            $trendMonths[] = $meta['label'];
            $trendRevenues[] = round($periodData[$key]['revenue'] ?? 0.0, 2);
            $trendExpenses[] = round($periodData[$key]['expense'] ?? 0.0, 2);
        }

        return [
            'months' => $trendMonths,
            'revenues' => $trendRevenues,
            'expenses' => $trendExpenses,
        ];
    }

    /**
     * Generate Accounts Receivable (AR) Aging Report.
     */
    public function getArAgingReport(?string $asOfDate = null): array
    {
        $asOf = $asOfDate ? Carbon::parse($asOfDate) : Carbon::now();
        $asOfStr = $asOf->toDateString();

        $invoices = Invoice::with('customer')
            ->whereIn('status', ['sent', 'partially_paid', 'overdue'])
            ->whereDate('issue_date', '<=', $asOfStr)
            ->get();

        $customers = [];
        $grandTotals = [
            'current' => 0.0,
            'days_1_30' => 0.0,
            'days_31_60' => 0.0,
            'days_61_90' => 0.0,
            'days_over_90' => 0.0,
            'total' => 0.0,
        ];

        foreach ($invoices as $inv) {
            $balance = (float) $inv->total_amount - (float) $inv->paid_amount;
            if ($balance <= 0.0001) {
                continue;
            }

            $cid = $inv->customer_id;
            if (! isset($customers[$cid])) {
                $customers[$cid] = [
                    'customer' => $inv->customer,
                    'current' => 0.0,
                    'days_1_30' => 0.0,
                    'days_31_60' => 0.0,
                    'days_61_90' => 0.0,
                    'days_over_90' => 0.0,
                    'total' => 0.0,
                ];
            }

            $dueDate = Carbon::parse($inv->due_date);
            $daysOverdue = $dueDate->lt($asOf) ? (int) $dueDate->diffInDays($asOf, true) : 0;

            if ($dueDate->gte($asOf)) {
                $bucket = 'current';
            } elseif ($daysOverdue <= 30) {
                $bucket = 'days_1_30';
            } elseif ($daysOverdue <= 60) {
                $bucket = 'days_31_60';
            } elseif ($daysOverdue <= 90) {
                $bucket = 'days_61_90';
            } else {
                $bucket = 'days_over_90';
            }

            $customers[$cid][$bucket] += $balance;
            $customers[$cid]['total'] += $balance;
            $grandTotals[$bucket] += $balance;
            $grandTotals['total'] += $balance;
        }

        return [
            'as_of_date' => $asOfStr,
            'customers' => array_values($customers),
            'totals' => $grandTotals,
        ];
    }

    /**
     * Generate Accounts Payable (AP) Aging Report.
     */
    public function getApAgingReport(?string $asOfDate = null): array
    {
        $asOf = $asOfDate ? Carbon::parse($asOfDate) : Carbon::now();
        $asOfStr = $asOf->toDateString();

        $bills = Bill::with('vendor')
            ->whereIn('status', ['approved', 'partially_paid', 'overdue'])
            ->whereDate('bill_date', '<=', $asOfStr)
            ->get();

        $vendors = [];
        $grandTotals = [
            'current' => 0.0,
            'days_1_30' => 0.0,
            'days_31_60' => 0.0,
            'days_61_90' => 0.0,
            'days_over_90' => 0.0,
            'total' => 0.0,
        ];

        foreach ($bills as $bill) {
            $balance = (float) $bill->total_amount - (float) $bill->paid_amount;
            if ($balance <= 0.0001) {
                continue;
            }

            $vid = $bill->vendor_id;
            if (! isset($vendors[$vid])) {
                $vendors[$vid] = [
                    'vendor' => $bill->vendor,
                    'current' => 0.0,
                    'days_1_30' => 0.0,
                    'days_31_60' => 0.0,
                    'days_61_90' => 0.0,
                    'days_over_90' => 0.0,
                    'total' => 0.0,
                ];
            }

            $dueDate = Carbon::parse($bill->due_date);
            $daysOverdue = $dueDate->lt($asOf) ? (int) $dueDate->diffInDays($asOf, true) : 0;

            if ($dueDate->gte($asOf)) {
                $bucket = 'current';
            } elseif ($daysOverdue <= 30) {
                $bucket = 'days_1_30';
            } elseif ($daysOverdue <= 60) {
                $bucket = 'days_31_60';
            } elseif ($daysOverdue <= 90) {
                $bucket = 'days_61_90';
            } else {
                $bucket = 'days_over_90';
            }

            $vendors[$vid][$bucket] += $balance;
            $vendors[$vid]['total'] += $balance;
            $grandTotals[$bucket] += $balance;
            $grandTotals['total'] += $balance;
        }

        return [
            'as_of_date' => $asOfStr,
            'vendors' => array_values($vendors),
            'totals' => $grandTotals,
        ];
    }

    /**
     * Generate Statement of Cash Flows (Operating, Investing, Financing).
     */
    public function getCashFlowStatement(string $startDate, string $endDate): array
    {
        $incomeStmt = $this->getIncomeStatement($startDate, $endDate);
        $netIncome = $incomeStmt['net_income'];

        $priorDate = Carbon::parse($startDate)->subDay()->toDateString();
        $priorBalances = $this->getBatchAccountBalances($priorDate);
        $currentBalances = $this->getBatchAccountBalances($endDate);

        $accounts = Account::all()->keyBy('id');

        $depreciation = 0.0;
        $operatingAdjustments = [];
        $investingActivities = [];
        $financingActivities = [];

        $beginningCash = 0.0;
        $endingCash = 0.0;

        foreach ($accounts as $id => $acc) {
            $prior = $priorBalances[$id] ?? 0.0;
            $current = $currentBalances[$id] ?? 0.0;
            $delta = $current - $prior;

            // Cash and Bank accounts (code 10xx or sub_type current_asset with cash/bank)
            if ($acc->type === 'asset' && ($acc->code >= '1000' && $acc->code <= '1099')) {
                $beginningCash += $prior;
                $endingCash += $current;

                continue;
            }

            // Depreciation
            if (str_contains(strtolower($acc->name), 'depreciation')) {
                $depreciation += abs($delta);
            }

            // Working capital items
            if ($acc->sub_type === 'current_asset') {
                // Asset increase is cash outflow (negative)
                $impact = -$delta;
                $operatingAdjustments[] = [
                    'account' => $acc,
                    'name' => 'Change in '.$acc->name,
                    'amount' => $impact,
                ];
            } elseif ($acc->sub_type === 'current_liability') {
                // Liability increase is cash inflow (positive)
                $operatingAdjustments[] = [
                    'account' => $acc,
                    'name' => 'Change in '.$acc->name,
                    'amount' => $delta,
                ];
            } elseif ($acc->sub_type === 'non_current_asset') {
                // Investing (Capital expenditure / asset purchase)
                $impact = -$delta;
                if (abs($impact) > 0.0001) {
                    $investingActivities[] = [
                        'account' => $acc,
                        'name' => 'Capital Activity: '.$acc->name,
                        'amount' => $impact,
                    ];
                }
            } elseif ($acc->type === 'equity' && ! in_array($acc->code, ['3990'])) {
                // Financing
                if (abs($delta) > 0.0001) {
                    $financingActivities[] = [
                        'account' => $acc,
                        'name' => 'Equity Activity: '.$acc->name,
                        'amount' => $delta,
                    ];
                }
            } elseif ($acc->sub_type === 'non_current_liability') {
                if (abs($delta) > 0.0001) {
                    $financingActivities[] = [
                        'account' => $acc,
                        'name' => 'Debt Financing: '.$acc->name,
                        'amount' => $delta,
                    ];
                }
            }
        }

        $operatingCashFlow = $netIncome;
        foreach ($operatingAdjustments as $adj) {
            $operatingCashFlow += $adj['amount'];
        }

        $investingCashFlow = 0.0;
        foreach ($investingActivities as $act) {
            $investingCashFlow += $act['amount'];
        }

        $financingCashFlow = 0.0;
        foreach ($financingActivities as $act) {
            $financingCashFlow += $act['amount'];
        }

        $netCashChange = $operatingCashFlow + $investingCashFlow + $financingCashFlow;

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'net_income' => $netIncome,
            'depreciation' => $depreciation,
            'operating_adjustments' => $operatingAdjustments,
            'operating_cash_flow' => $operatingCashFlow,
            'investing_activities' => $investingActivities,
            'investing_cash_flow' => $investingCashFlow,
            'financing_activities' => $financingActivities,
            'financing_cash_flow' => $financingCashFlow,
            'net_cash_change' => $netCashChange,
            'beginning_cash' => $beginningCash,
            'ending_cash' => $endingCash,
        ];
    }

    /**
     * Export any financial report to a downloadable CSV stream.
     */
    public function exportToCsv(string $reportType, array $data): StreamedResponse
    {
        $filename = "report_{$reportType}_".date('Ymd_His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($reportType, $data) {
            $handle = fopen('php://output', 'w');

            switch ($reportType) {
                case 'trial_balance':
                    fputcsv($handle, ['Code', 'Account Name', 'Type', 'Normal Balance', 'Debit', 'Credit', 'Ending Debit', 'Ending Credit']);
                    foreach ($data['rows'] as $r) {
                        fputcsv($handle, [
                            $r['code'],
                            $r['name'],
                            $r['type'],
                            $r['normal_balance'],
                            number_format($r['debit'], 2, '.', ''),
                            number_format($r['credit'], 2, '.', ''),
                            number_format($r['ending_debit'], 2, '.', ''),
                            number_format($r['ending_credit'], 2, '.', ''),
                        ]);
                    }
                    fputcsv($handle, ['TOTALS', '', '', '', '', '', number_format($data['total_debit'], 2, '.', ''), number_format($data['total_credit'], 2, '.', '')]);
                    break;

                case 'balance_sheet':
                    fputcsv($handle, ['Classification', 'Code', 'Account Name', 'Balance']);
                    foreach ($data['assets'] as $a) {
                        fputcsv($handle, ['Assets', $a['account']->code, $a['account']->name, number_format($a['balance'], 2, '.', '')]);
                    }
                    fputcsv($handle, ['TOTAL ASSETS', '', '', number_format($data['total_assets'], 2, '.', '')]);
                    foreach ($data['liabilities'] as $l) {
                        fputcsv($handle, ['Liabilities', $l['account']->code, $l['account']->name, number_format($l['balance'], 2, '.', '')]);
                    }
                    fputcsv($handle, ['TOTAL LIABILITIES', '', '', number_format($data['total_liabilities'], 2, '.', '')]);
                    foreach ($data['equity'] as $e) {
                        fputcsv($handle, ['Equity', $e['account']->code, $e['account']->name, number_format($e['balance'], 2, '.', '')]);
                    }
                    fputcsv($handle, ['Current Period Earnings', '', '', number_format($data['current_year_earnings'], 2, '.', '')]);
                    fputcsv($handle, ['TOTAL LIABILITIES & EQUITY', '', '', number_format($data['total_liabilities_and_equity'], 2, '.', '')]);
                    break;

                case 'income_statement':
                    fputcsv($handle, ['Section', 'Code', 'Account Name', 'Amount']);
                    foreach ($data['revenues'] as $r) {
                        fputcsv($handle, ['Operating Revenue', $r['account']->code, $r['account']->name, number_format($r['balance'], 2, '.', '')]);
                    }
                    fputcsv($handle, ['TOTAL REVENUE', '', '', number_format($data['total_revenue'], 2, '.', '')]);
                    foreach ($data['cogs'] as $c) {
                        fputcsv($handle, ['Cost of Goods Sold', $c['account']->code, $c['account']->name, number_format($c['balance'], 2, '.', '')]);
                    }
                    fputcsv($handle, ['GROSS PROFIT', '', '', number_format($data['gross_profit'], 2, '.', '')]);
                    foreach ($data['operating_expenses'] as $oe) {
                        fputcsv($handle, ['Operating Expenses', $oe['account']->code, $oe['account']->name, number_format($oe['balance'], 2, '.', '')]);
                    }
                    fputcsv($handle, ['NET INCOME', '', '', number_format($data['net_income'], 2, '.', '')]);
                    break;

                case 'ar_aging':
                    fputcsv($handle, ['Customer Name', 'Current', '1-30 Days', '31-60 Days', '61-90 Days', '>90 Days', 'Total Overdue']);
                    foreach ($data['customers'] as $c) {
                        fputcsv($handle, [
                            $c['customer']->name,
                            number_format($c['current'], 2, '.', ''),
                            number_format($c['days_1_30'], 2, '.', ''),
                            number_format($c['days_31_60'], 2, '.', ''),
                            number_format($c['days_61_90'], 2, '.', ''),
                            number_format($c['days_over_90'], 2, '.', ''),
                            number_format($c['total'], 2, '.', ''),
                        ]);
                    }
                    fputcsv($handle, [
                        'TOTALS',
                        number_format($data['totals']['current'], 2, '.', ''),
                        number_format($data['totals']['days_1_30'], 2, '.', ''),
                        number_format($data['totals']['days_31_60'], 2, '.', ''),
                        number_format($data['totals']['days_61_90'], 2, '.', ''),
                        number_format($data['totals']['days_over_90'], 2, '.', ''),
                        number_format($data['totals']['total'], 2, '.', ''),
                    ]);
                    break;

                case 'ap_aging':
                    fputcsv($handle, ['Vendor Name', 'Current', '1-30 Days', '31-60 Days', '61-90 Days', '>90 Days', 'Total Payable']);
                    foreach ($data['vendors'] as $v) {
                        fputcsv($handle, [
                            $v['vendor']->name,
                            number_format($v['current'], 2, '.', ''),
                            number_format($v['days_1_30'], 2, '.', ''),
                            number_format($v['days_31_60'], 2, '.', ''),
                            number_format($v['days_61_90'], 2, '.', ''),
                            number_format($v['days_over_90'], 2, '.', ''),
                            number_format($v['total'], 2, '.', ''),
                        ]);
                    }
                    fputcsv($handle, [
                        'TOTALS',
                        number_format($data['totals']['current'], 2, '.', ''),
                        number_format($data['totals']['days_1_30'], 2, '.', ''),
                        number_format($data['totals']['days_31_60'], 2, '.', ''),
                        number_format($data['totals']['days_61_90'], 2, '.', ''),
                        number_format($data['totals']['days_over_90'], 2, '.', ''),
                        number_format($data['totals']['total'], 2, '.', ''),
                    ]);
                    break;

                case 'cash_flow':
                    fputcsv($handle, ['Category', 'Line Item', 'Amount']);
                    fputcsv($handle, ['Operating Activities', 'Net Income', number_format($data['net_income'], 2, '.', '')]);
                    if ($data['depreciation'] > 0) {
                        fputcsv($handle, ['Operating Activities', 'Depreciation Non-Cash Adjustment', number_format($data['depreciation'], 2, '.', '')]);
                    }
                    foreach ($data['operating_adjustments'] as $oa) {
                        fputcsv($handle, ['Operating Activities', $oa['name'], number_format($oa['amount'], 2, '.', '')]);
                    }
                    fputcsv($handle, ['OPERATING ACTIVITIES TOTAL', '', number_format($data['operating_cash_flow'], 2, '.', '')]);
                    foreach ($data['investing_activities'] as $ia) {
                        fputcsv($handle, ['Investing Activities', $ia['name'], number_format($ia['amount'], 2, '.', '')]);
                    }
                    fputcsv($handle, ['INVESTING ACTIVITIES TOTAL', '', number_format($data['investing_cash_flow'], 2, '.', '')]);
                    foreach ($data['financing_activities'] as $fa) {
                        fputcsv($handle, ['Financing Activities', $fa['name'], number_format($fa['amount'], 2, '.', '')]);
                    }
                    fputcsv($handle, ['FINANCING ACTIVITIES TOTAL', '', number_format($data['financing_cash_flow'], 2, '.', '')]);
                    fputcsv($handle, ['NET CHANGE IN CASH', '', number_format($data['net_cash_change'], 2, '.', '')]);
                    fputcsv($handle, ['Cash Balance at Beginning of Period', '', number_format($data['beginning_cash'], 2, '.', '')]);
                    fputcsv($handle, ['Cash Balance at End of Period', '', number_format($data['ending_cash'], 2, '.', '')]);
                    break;
            }

            fclose($handle);
        }, 200, $headers);
    }
}
