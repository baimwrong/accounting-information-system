<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\BankReconciliation;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BankReconciliationController extends Controller
{
    public function index(Request $request): View
    {
        $query = BankReconciliation::with(['bankAccount', 'creator', 'completer'])->latest('statement_date')->latest('id');

        if ($request->filled('bank_account_id')) {
            $query->where('bank_account_id', $request->input('bank_account_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $reconciliations = $query->paginate(15)->withQueryString();

        $bankAccounts = Account::where('type', 'asset')
            ->where(function ($q) {
                $q->where('code', 'LIKE', '10%')
                    ->orWhere('name', 'LIKE', '%Cash%')
                    ->orWhere('name', 'LIKE', '%Bank%');
            })
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('bank_reconciliations.index', compact('reconciliations', 'bankAccounts'));
    }

    public function create(): View
    {
        $bankAccounts = Account::where('type', 'asset')
            ->where(function ($q) {
                $q->where('code', 'LIKE', '10%')
                    ->orWhere('name', 'LIKE', '%Cash%')
                    ->orWhere('name', 'LIKE', '%Bank%');
            })
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('bank_reconciliations.create', compact('bankAccounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bank_account_id' => ['required', 'exists:accounts,id'],
            'statement_date' => ['required', 'date'],
            'statement_balance' => ['required', 'numeric'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $statementBal = round((float) $data['statement_balance'], 4);

        $reconciliation = BankReconciliation::create([
            'bank_account_id' => $data['bank_account_id'],
            'statement_date' => $data['statement_date'],
            'statement_balance' => $statementBal,
            'reconciled_balance' => 0.0,
            'difference' => $statementBal,
            'status' => 'draft',
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        AuditLog::record(
            $request->user(),
            'create_bank_reconciliation',
            "Opened bank reconciliation worksheet for {$reconciliation->bankAccount->name} as of {$reconciliation->statement_date->format('Y-m-d')}",
            $reconciliation
        );

        return redirect()->route('bank-reconciliations.show', $reconciliation)
            ->with('success', 'Reconciliation worksheet created. Select matching cleared transactions.');
    }

    public function show(BankReconciliation $bankReconciliation): View
    {
        $bankReconciliation->load(['bankAccount', 'creator', 'completer']);

        $payments = Payment::with('contact')
            ->where('bank_account_id', $bankReconciliation->bank_account_id)
            ->whereDate('payment_date', '<=', $bankReconciliation->statement_date)
            ->where(function ($q) use ($bankReconciliation) {
                $q->whereNull('bank_reconciliation_id')
                    ->orWhere('bank_reconciliation_id', $bankReconciliation->id);
            })
            ->orderBy('payment_date')
            ->get();

        // Calculate totals
        $clearedReceipts = 0.0;
        $clearedDisbursements = 0.0;

        foreach ($payments as $p) {
            if ($p->bank_reconciliation_id === $bankReconciliation->id) {
                if ($p->type === 'received') {
                    $clearedReceipts += (float) $p->amount;
                } else {
                    $clearedDisbursements += (float) $p->amount;
                }
            }
        }

        $clearedBalance = round($clearedReceipts - $clearedDisbursements, 4);
        $difference = round((float) $bankReconciliation->statement_balance - $clearedBalance, 4);

        if ($bankReconciliation->isDraft()) {
            $bankReconciliation->update([
                'reconciled_balance' => $clearedBalance,
                'difference' => $difference,
            ]);
        }

        return view('bank_reconciliations.show', compact(
            'bankReconciliation',
            'payments',
            'clearedReceipts',
            'clearedDisbursements',
            'clearedBalance',
            'difference'
        ));
    }

    public function togglePayment(Request $request, BankReconciliation $bankReconciliation, Payment $payment): RedirectResponse
    {
        if ($bankReconciliation->isCompleted()) {
            return back()->with('error', 'Cannot modify transactions on a completed and locked reconciliation.');
        }

        if ($payment->bank_account_id !== $bankReconciliation->bank_account_id) {
            return back()->with('error', 'Transaction does not belong to this bank account.');
        }

        if ($payment->bank_reconciliation_id === $bankReconciliation->id) {
            // Unclear payment
            $payment->update([
                'bank_reconciliation_id' => null,
                'reconciled_at' => null,
            ]);
        } else {
            // Clear payment
            $payment->update([
                'bank_reconciliation_id' => $bankReconciliation->id,
                'reconciled_at' => Carbon::now(),
            ]);
        }

        return back();
    }

    public function clearAll(Request $request, BankReconciliation $bankReconciliation): RedirectResponse
    {
        if ($bankReconciliation->isCompleted()) {
            return back()->with('error', 'Cannot modify transactions on a completed reconciliation.');
        }

        Payment::where('bank_account_id', $bankReconciliation->bank_account_id)
            ->whereDate('payment_date', '<=', $bankReconciliation->statement_date)
            ->whereNull('bank_reconciliation_id')
            ->update([
                'bank_reconciliation_id' => $bankReconciliation->id,
                'reconciled_at' => Carbon::now(),
            ]);

        return back()->with('success', 'All matching payments marked as cleared.');
    }

    public function unclearAll(Request $request, BankReconciliation $bankReconciliation): RedirectResponse
    {
        if ($bankReconciliation->isCompleted()) {
            return back()->with('error', 'Cannot modify transactions on a completed reconciliation.');
        }

        Payment::where('bank_reconciliation_id', $bankReconciliation->id)
            ->update([
                'bank_reconciliation_id' => null,
                'reconciled_at' => null,
            ]);

        return back()->with('success', 'Cleared transactions reset.');
    }

    public function complete(Request $request, BankReconciliation $bankReconciliation): RedirectResponse
    {
        if ($bankReconciliation->isCompleted()) {
            return back()->with('info', 'This reconciliation is already completed and locked.');
        }

        if (abs((float) $bankReconciliation->difference) > 0.01) {
            return back()->with('error', 'Cannot complete reconciliation while there is an unresolved difference. The difference must be zero ($0.00).');
        }

        $bankReconciliation->update([
            'status' => 'completed',
            'completed_at' => Carbon::now(),
            'completed_by' => $request->user()->id,
        ]);

        AuditLog::record(
            $request->user(),
            'complete_bank_reconciliation',
            "Completed and locked bank reconciliation for {$bankReconciliation->bankAccount->name} as of {$bankReconciliation->statement_date->format('Y-m-d')} (Balance: \${$bankReconciliation->reconciled_balance})",
            $bankReconciliation
        );

        return back()->with('success', 'Bank reconciliation successfully verified and locked.');
    }
}
