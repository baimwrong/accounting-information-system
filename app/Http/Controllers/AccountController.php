<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Product;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    public function index(Request $request): View
    {
        $query = Account::with('parent')->orderBy('code');

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'LIKE', "%{$search}%")
                    ->orWhere('name', 'LIKE', "%{$search}%");
            });
        }

        $accounts = $query->paginate(25)->withQueryString();
        $accountIds = $accounts->pluck('id')->all();
        $balances = $this->reportService->getBatchAccountBalances(null, $accountIds);
        foreach ($accounts as $acc) {
            $acc->current_balance = $balances[$acc->id] ?? 0.0;
        }

        $parentAccounts = Account::orderBy('code')->get();

        return view('accounts.index', compact('accounts', 'parentAccounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:accounts,code'],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:asset,liability,equity,revenue,expense'],
            'sub_type' => ['nullable', 'string', 'max:50'],
            'normal_balance' => ['required', 'in:debit,credit'],
            'parent_id' => ['nullable', 'exists:accounts,id'],
            'description' => ['nullable', 'string'],
        ]);

        $account = Account::create($data);

        AuditLog::record(
            $request->user(),
            'create_account',
            "Created account {$account->code} - {$account->name} ({$account->type})",
            $account
        );

        return redirect()->route('accounts.index')->with('success', "Account {$account->code} created successfully.");
    }

    public function edit(Account $account): View
    {
        $parentAccounts = Account::where('id', '!=', $account->id)->orderBy('code')->get();

        return view('accounts.edit', compact('account', 'parentAccounts'));
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:accounts,code,'.$account->id],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:asset,liability,equity,revenue,expense'],
            'sub_type' => ['nullable', 'string', 'max:50'],
            'normal_balance' => ['required', 'in:debit,credit'],
            'parent_id' => ['nullable', 'exists:accounts,id'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $account->update($data);

        AuditLog::record(
            $request->user(),
            'update_account',
            "Updated account {$account->code} - {$account->name}",
            $account
        );

        return redirect()->route('accounts.index')->with('success', "Account {$account->code} updated successfully.");
    }

    public function destroy(Request $request, Account $account): RedirectResponse
    {
        if ($account->is_system) {
            return back()->with('error', 'System core accounts cannot be deleted.');
        }

        if ($account->journalEntryItems()->exists()) {
            return back()->with('error', 'Cannot delete account because it has recorded journal entries. You can deactivate it instead.');
        }

        if ($account->children()->exists()) {
            return back()->with('error', 'Cannot delete account because it has sub-accounts attached. Reassign or delete sub-accounts first.');
        }

        if (Product::where('income_account_id', $account->id)->orWhere('expense_account_id', $account->id)->orWhere('asset_account_id', $account->id)->exists()) {
            return back()->with('error', 'Cannot delete account because it is assigned as an income, expense, or asset account on products.');
        }

        if (Payment::where('bank_account_id', $account->id)->exists()) {
            return back()->with('error', 'Cannot delete account because it is assigned as a bank/cash account on recorded payments.');
        }

        $code = $account->code;
        $name = $account->name;
        $account->delete();

        AuditLog::record(
            $request->user(),
            'delete_account',
            "Deleted account {$code} - {$name}"
        );

        return redirect()->route('accounts.index')->with('success', "Account {$code} deleted.");
    }

    public function statement(Request $request, Account $account): View
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $ledgerData = $this->reportService->getGeneralLedgerAccount($account->id, $startDate, $endDate);

        return view('accounts.statement', array_merge($ledgerData, [
            'account' => $account,
        ]));
    }
}
