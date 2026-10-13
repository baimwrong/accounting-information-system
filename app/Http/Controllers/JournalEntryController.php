<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\AccountingService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JournalEntryController extends Controller
{
    public function __construct(
        protected AccountingService $accountingService
    ) {}

    public function index(Request $request): View
    {
        $query = JournalEntry::with(['creator', 'poster'])->latest('date')->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('start_date')) {
            $query->where('date', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->where('date', '<=', $request->input('end_date'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('entry_number', 'LIKE', "%{$search}%")
                    ->orWhere('reference', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        $entries = $query->paginate(20)->withQueryString();

        return view('journal_entries.index', compact('entries'));
    }

    public function create(): View
    {
        $accounts = Account::where('is_active', true)->orderBy('code')->get();
        $suggestedNumber = $this->accountingService->generateEntryNumber();

        return view('journal_entries.create', compact('accounts', 'suggestedNumber'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string'],
            'items' => ['required', 'array', 'min:2'],
            'items.*.account_id' => ['required', 'exists:accounts,id'],
            'items.*.type' => ['required', 'in:debit,credit'],
            'items.*.amount' => ['required', 'numeric', 'min:0.01'],
            'items.*.memo' => ['nullable', 'string', 'max:255'],
            'auto_post' => ['nullable', 'boolean'],
        ]);

        try {
            $autoPost = $request->boolean('auto_post', true);
            $entry = $this->accountingService->createJournalEntry([
                'date' => $request->input('date'),
                'reference' => $request->input('reference'),
                'description' => $request->input('description'),
                'items' => $request->input('items'),
            ], $request->user(), $autoPost);

            return redirect()->route('journal-entries.show', $entry)
                ->with('success', "Journal Entry {$entry->entry_number} created successfully.");
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(JournalEntry $journalEntry): View
    {
        $journalEntry->load(['items.account', 'creator', 'poster']);

        return view('journal_entries.show', compact('journalEntry'));
    }

    public function post(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        try {
            $this->accountingService->postJournalEntry($journalEntry, $request->user());

            return back()->with('success', "Journal Entry {$journalEntry->entry_number} has been posted to General Ledger.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function void(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->accountingService->voidJournalEntry($journalEntry, $request->user(), $request->input('reason'));

            return back()->with('success', "Journal Entry {$journalEntry->entry_number} has been voided.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
