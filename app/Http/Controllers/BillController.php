<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Contact;
use App\Models\Payment;
use App\Models\Product;
use App\Services\AccountingService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BillController extends Controller
{
    public function __construct(
        protected AccountingService $accountingService
    ) {}

    public function index(Request $request): View
    {
        $query = Bill::with(['vendor', 'journalEntry'])->latest('bill_date')->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->input('vendor_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('bill_number', 'LIKE', "%{$search}%")
                    ->orWhereHas('vendor', fn ($v) => $v->where('name', 'LIKE', "%{$search}%"));
            });
        }

        $bills = $query->paginate(20)->withQueryString();
        $vendors = Contact::vendors()->active()->orderBy('name')->get();

        return view('bills.index', compact('bills', 'vendors'));
    }

    public function create(): View
    {
        $vendors = Contact::vendors()->active()->orderBy('name')->get();
        $products = Product::active()->orderBy('name')->get();
        $expenseAccounts = Account::whereIn('type', ['expense', 'asset'])->where('is_active', true)->orderBy('code')->get();

        $ym = Carbon::now()->format('Ym');
        $latest = Bill::where('bill_number', 'LIKE', "BILL-{$ym}-%")->latest('id')->first();
        $seq = 1;
        if ($latest) {
            $parts = explode('-', $latest->bill_number);
            $seq = ((int) end($parts)) + 1;
        }
        $billNumber = sprintf('BILL-%s-%04d', $ym, $seq);

        return view('bills.create', compact('vendors', 'products', 'expenseAccounts', 'billNumber'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'vendor_id' => ['required', 'exists:contacts,id'],
            'bill_number' => ['required', 'string', 'max:50', 'unique:bills,bill_number'],
            'bill_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:bill_date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.account_id' => ['required', 'exists:accounts,id'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        try {
            $bill = DB::transaction(function () use ($request) {
                $subtotal = 0.0;
                $taxTotal = 0.0;

                $calculatedItems = [];

                foreach ($request->input('items') as $raw) {
                    $qty = (float) $raw['quantity'];
                    $price = (float) $raw['unit_price'];
                    $taxPct = (float) ($raw['tax_percent'] ?? 0);

                    $baseTotal = $qty * $price;
                    $taxAmt = $baseTotal * ($taxPct / 100);
                    $lineTotal = $baseTotal + $taxAmt;

                    $subtotal += $baseTotal;
                    $taxTotal += $taxAmt;

                    $calculatedItems[] = [
                        'product_id' => ! empty($raw['product_id']) ? $raw['product_id'] : null,
                        'account_id' => $raw['account_id'],
                        'description' => $raw['description'],
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'tax_percent' => $taxPct,
                        'subtotal' => round($baseTotal, 4),
                        'tax_amount' => round($taxAmt, 4),
                        'total' => round($lineTotal, 4),
                    ];
                }

                $totalAmount = $subtotal + $taxTotal;

                $bill = Bill::create([
                    'bill_number' => $request->input('bill_number'),
                    'vendor_id' => $request->input('vendor_id'),
                    'bill_date' => $request->input('bill_date'),
                    'due_date' => $request->input('due_date'),
                    'subtotal' => round($subtotal, 4),
                    'tax_total' => round($taxTotal, 4),
                    'total_amount' => round($totalAmount, 4),
                    'paid_amount' => 0,
                    'status' => 'draft',
                    'notes' => $request->input('notes'),
                    'created_by' => $request->user()->id,
                ]);

                foreach ($calculatedItems as $itemData) {
                    $bill->items()->create($itemData);
                }

                // Automatically post journal entry to General Ledger
                $this->accountingService->postBill($bill, $request->user());

                AuditLog::record(
                    $request->user(),
                    'create_bill',
                    "Created and posted vendor bill {$bill->bill_number} from {$bill->vendor->name} (Total: {$bill->total_amount})",
                    $bill
                );

                return $bill;
            });

            return redirect()->route('bills.show', $bill)
                ->with('success', "Bill {$bill->bill_number} created and posted to General Ledger.");
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(Bill $bill): View
    {
        $bill->load(['vendor', 'items.account', 'items.product', 'payments.bankAccount', 'journalEntry.items.account']);
        $bankAccounts = Account::where('type', 'asset')
            ->where(function ($q) {
                $q->where('code', 'LIKE', '10%')
                    ->orWhere('name', 'LIKE', '%Cash%')
                    ->orWhere('name', 'LIKE', '%Bank%');
            })
            ->get();

        return view('bills.show', compact('bill', 'bankAccounts'));
    }

    public function recordPayment(Request $request, Bill $bill): RedirectResponse
    {
        $remaining = $bill->remaining_amount;

        $data = $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$remaining],
            'bank_account_id' => ['required', 'exists:accounts,id'],
            'payment_method' => ['required', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            DB::transaction(function () use ($data, $bill, $request) {
                $ym = Carbon::now()->format('Ym');
                $latest = Payment::where('payment_number', 'LIKE', "DIS-{$ym}-%")->latest('id')->first();
                $seq = 1;
                if ($latest) {
                    $parts = explode('-', $latest->payment_number);
                    $seq = ((int) end($parts)) + 1;
                }
                $paymentNumber = sprintf('DIS-%s-%04d', $ym, $seq);

                $payment = Payment::create([
                    'payment_number' => $paymentNumber,
                    'type' => 'sent',
                    'contact_id' => $bill->vendor_id,
                    'bill_id' => $bill->id,
                    'bank_account_id' => $data['bank_account_id'],
                    'payment_date' => $data['payment_date'],
                    'amount' => round((float) $data['amount'], 4),
                    'payment_method' => $data['payment_method'],
                    'reference' => $data['reference'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $request->user()->id,
                ]);

                $this->accountingService->postBillPayment($payment, $request->user());

                AuditLog::record(
                    $request->user(),
                    'record_bill_payment',
                    "Paid {$payment->payment_number} ({$payment->amount}) for bill {$bill->bill_number}",
                    $payment
                );
            });

            return back()->with('success', 'Bill payment recorded and posted to General Ledger.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
