<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntryItem;
use Carbon\Carbon;

class ReportService
{
    /**
     * Generate Trial Balance.
     */
    public function getTrialBalance(?string $startDate = null, ?string $endDate = null): array
    {
        $accounts = Account::orderBy('code')->get();

        $rows = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($accounts as $account) {
            $query = JournalEntryItem::where('account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                    $q->where('status', 'posted');
                    if ($startDate) {
                        $q->where('date', '>=', $startDate);
                    }
                    if ($endDate) {
                        $q->where('date', '<=', $endDate);
                    }
                });

            $debit = (float) (clone $query)->where('type', 'debit')->sum('amount');
            $credit = (float) (clone $query)->where('type', 'credit')->sum('amount');

            // Skip zero activity accounts if wanted, but standard trial balance displays active COA
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
     * Generate Balance Sheet as of given date.
     */
    public function getBalanceSheet(?string $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?: Carbon::now()->toDateString();

        $assetAccounts = Account::where('type', 'asset')->orderBy('code')->get();
        $liabilityAccounts = Account::where('type', 'liability')->orderBy('code')->get();
        $equityAccounts = Account::where('type', 'equity')->orderBy('code')->get();

        $calculateBalance = function (Account $account) use ($asOfDate): float {
            $debit = (float) JournalEntryItem::where('account_id', $account->id)
                ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')->where('date', '<=', $asOfDate))
                ->where('type', 'debit')
                ->sum('amount');

            $credit = (float) JournalEntryItem::where('account_id', $account->id)
                ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')->where('date', '<=', $asOfDate))
                ->where('type', 'credit')
                ->sum('amount');

            return $account->normal_balance === 'debit' ? ($debit - $credit) : ($credit - $debit);
        };

        // Assets
        $assets = [];
        $totalAssets = 0.0;
        foreach ($assetAccounts as $acc) {
            $bal = $calculateBalance($acc);
            $assets[] = ['account' => $acc, 'balance' => $bal];
            $totalAssets += $bal;
        }

        // Liabilities
        $liabilities = [];
        $totalLiabilities = 0.0;
        foreach ($liabilityAccounts as $acc) {
            $bal = $calculateBalance($acc);
            $liabilities[] = ['account' => $acc, 'balance' => $bal];
            $totalLiabilities += $bal;
        }

        // Equity
        $equity = [];
        $totalEquityWithoutIncome = 0.0;
        foreach ($equityAccounts as $acc) {
            $bal = $calculateBalance($acc);
            $equity[] = ['account' => $acc, 'balance' => $bal];
            $totalEquityWithoutIncome += $bal;
        }

        // Calculate Net Income for period (Cumulative up to asOfDate)
        $revenueAccounts = Account::where('type', 'revenue')->get();
        $expenseAccounts = Account::where('type', 'expense')->get();

        $totalRevenue = 0.0;
        foreach ($revenueAccounts as $acc) {
            $totalRevenue += $calculateBalance($acc);
        }

        $totalExpense = 0.0;
        foreach ($expenseAccounts as $acc) {
            $totalExpense += $calculateBalance($acc);
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
     * Generate Income Statement (Profit & Loss).
     */
    public function getIncomeStatement(string $startDate, string $endDate): array
    {
        $revenueAccounts = Account::where('type', 'revenue')->orderBy('code')->get();
        $expenseAccounts = Account::where('type', 'expense')->orderBy('code')->get();

        $calculatePeriodBalance = function (Account $account) use ($startDate, $endDate): float {
            $debit = (float) JournalEntryItem::where('account_id', $account->id)
                ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')
                    ->whereBetween('date', [$startDate, $endDate]))
                ->where('type', 'debit')
                ->sum('amount');

            $credit = (float) JournalEntryItem::where('account_id', $account->id)
                ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')
                    ->whereBetween('date', [$startDate, $endDate]))
                ->where('type', 'credit')
                ->sum('amount');

            return $account->normal_balance === 'debit' ? ($debit - $credit) : ($credit - $debit);
        };

        $revenues = [];
        $totalRevenue = 0.0;
        foreach ($revenueAccounts as $acc) {
            $bal = $calculatePeriodBalance($acc);
            $revenues[] = ['account' => $acc, 'balance' => $bal];
            $totalRevenue += $bal;
        }

        $cogs = [];
        $totalCogs = 0.0;
        $operatingExpenses = [];
        $totalOperatingExpenses = 0.0;

        foreach ($expenseAccounts as $acc) {
            $bal = $calculatePeriodBalance($acc);
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

        // Calculate opening balance before startDate
        $openingBalance = 0.0;
        if ($startDate) {
            $priorDebit = (float) JournalEntryItem::where('account_id', $account->id)
                ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')->where('date', '<', $startDate))
                ->where('type', 'debit')
                ->sum('amount');

            $priorCredit = (float) JournalEntryItem::where('account_id', $account->id)
                ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted')->where('date', '<', $startDate))
                ->where('type', 'credit')
                ->sum('amount');

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
}
