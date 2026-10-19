<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Services\AccountingService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        protected AccountingService $accountingService
    ) {}

    public function index(Request $request): View
    {
        $query = Payment::with(['contact', 'bankAccount', 'invoice', 'bill'])->latest('payment_date')->latest('id');

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('payment_number', 'LIKE', "%{$search}%")
                    ->orWhere('reference', 'LIKE', "%{$search}%")
                    ->orWhereHas('contact', fn ($c) => $c->where('name', 'LIKE', "%{$search}%"));
            });
        }

        $payments = $query->paginate(20)->withQueryString();

        $bankAccounts = Account::where('type', 'asset')
            ->where(function ($q) {
                $q->where('code', 'LIKE', '10%')
                    ->orWhere('name', 'LIKE', '%Cash%')
                    ->orWhere('name', 'LIKE', '%Bank%');
            })
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('payments.index', compact('payments', 'bankAccounts'));
    }

    public function show(Payment $payment): View
    {
        $payment->load(['contact', 'bankAccount', 'invoice', 'bill', 'journalEntry.items.account', 'creator']);

        return view('payments.show', compact('payment'));
    }

    public function transfer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source_account_id' => ['required', 'exists:accounts,id'],
            'destination_account_id' => ['required', 'exists:accounts,id', 'different:source_account_id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $entry = $this->accountingService->transferBankFunds(
                (int) $data['source_account_id'],
                (int) $data['destination_account_id'],
                (float) $data['amount'],
                $data['date'],
                $data['reference'] ?? null,
                $data['description'] ?? null,
                $request->user()
            );

            AuditLog::record(
                $request->user(),
                'bank_transfer',
                "Inter-bank transfer of \${$data['amount']} posted as {$entry->entry_number}",
                $entry
            );

            return redirect()->route('payments.index')
                ->with('success', "Transfer of \${$data['amount']} successfully posted as Journal Entry {$entry->entry_number}.");
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}
