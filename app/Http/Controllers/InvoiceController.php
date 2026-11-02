<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Services\AccountingService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(
        protected AccountingService $accountingService
    ) {}

    public function index(Request $request): View
    {
        $query = Invoice::with(['customer', 'journalEntry'])->latest('issue_date')->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'LIKE', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'LIKE', "%{$search}%"));
            });
        }

        $invoices = $query->paginate(20)->withQueryString();
        $customers = Contact::customers()->active()->orderBy('name')->get();

        return view('invoices.index', compact('invoices', 'customers'));
    }

    public function create(): View
    {
        $customers = Contact::customers()->active()->orderBy('name')->get();
        $products = Product::active()->orderBy('name')->get();
        $revenueAccounts = Account::where('type', 'revenue')->where('is_active', true)->orderBy('code')->get();

        $invoiceNumber = $this->accountingService->generateInvoiceNumber();

        return view('invoices.create', compact('customers', 'products', 'revenueAccounts', 'invoiceNumber'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'customer_id' => ['required', 'exists:contacts,id'],
            'invoice_number' => ['nullable', 'string', 'max:50'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.account_id' => ['required', 'exists:accounts,id'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        try {
            $invoice = DB::transaction(function () use ($request) {
                $subtotal = 0.0;
                $taxTotal = 0.0;
                $discountTotal = 0.0;

                $calculatedItems = [];

                foreach ($request->input('items') as $raw) {
                    $qty = (float) $raw['quantity'];
                    $price = (float) $raw['unit_price'];
                    $taxPct = (float) ($raw['tax_percent'] ?? 0);
                    $discPct = (float) ($raw['discount_percent'] ?? 0);

                    $baseTotal = $qty * $price;
                    $discountAmt = $baseTotal * ($discPct / 100);
                    $afterDiscount = $baseTotal - $discountAmt;
                    $taxAmt = $afterDiscount * ($taxPct / 100);
                    $lineTotal = $afterDiscount + $taxAmt;

                    $subtotal += $afterDiscount;
                    $taxTotal += $taxAmt;
                    $discountTotal += $discountAmt;

                    $calculatedItems[] = [
                        'product_id' => ! empty($raw['product_id']) ? $raw['product_id'] : null,
                        'account_id' => $raw['account_id'],
                        'description' => $raw['description'],
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'tax_percent' => $taxPct,
                        'discount_percent' => $discPct,
                        'subtotal' => round($afterDiscount, 4),
                        'tax_amount' => round($taxAmt, 4),
                        'total' => round($lineTotal, 4),
                    ];
                }

                $totalAmount = $subtotal + $taxTotal;

                $invoiceNumber = $request->input('invoice_number');
                if (empty($invoiceNumber) || Invoice::where('invoice_number', $invoiceNumber)->exists()) {
                    $invoiceNumber = $this->accountingService->generateInvoiceNumber();
                }

                $invoice = Invoice::create([
                    'invoice_number' => $invoiceNumber,
                    'customer_id' => $request->input('customer_id'),
                    'issue_date' => $request->input('issue_date'),
                    'due_date' => $request->input('due_date'),
                    'subtotal' => round($subtotal, 4),
                    'tax_total' => round($taxTotal, 4),
                    'discount_total' => round($discountTotal, 4),
                    'total_amount' => round($totalAmount, 4),
                    'paid_amount' => 0,
                    'status' => 'draft',
                    'notes' => $request->input('notes'),
                    'created_by' => $request->user()->id,
                ]);

                foreach ($calculatedItems as $itemData) {
                    $invoice->items()->create($itemData);
                }

                // Automatically post journal entry to General Ledger
                $this->accountingService->postInvoice($invoice, $request->user());

                AuditLog::record(
                    $request->user(),
                    'create_invoice',
                    "Created and posted sales invoice {$invoice->invoice_number} for {$invoice->customer->name} (Total: {$invoice->total_amount})",
                    $invoice
                );

                return $invoice;
            });

            return redirect()->route('invoices.show', $invoice)
                ->with('success', "Invoice {$invoice->invoice_number} created and posted to General Ledger.");
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load(['customer', 'items.account', 'items.product', 'payments.bankAccount', 'journalEntry.items.account']);
        $bankAccounts = Account::where('type', 'asset')
            ->where(function ($q) {
                $q->where('code', 'LIKE', '10%')
                    ->orWhere('name', 'LIKE', '%Cash%')
                    ->orWhere('name', 'LIKE', '%Bank%');
            })
            ->get();

        return view('invoices.show', compact('invoice', 'bankAccounts'));
    }

    public function recordPayment(Request $request, Invoice $invoice): RedirectResponse
    {
        $remaining = $invoice->remaining_amount;

        $data = $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$remaining],
            'bank_account_id' => ['required', 'exists:accounts,id'],
            'payment_method' => ['required', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            DB::transaction(function () use ($data, $invoice, $request) {
                $paymentNumber = $this->accountingService->generatePaymentNumber('received');

                $payment = Payment::create([
                    'payment_number' => $paymentNumber,
                    'type' => 'received',
                    'contact_id' => $invoice->customer_id,
                    'invoice_id' => $invoice->id,
                    'bank_account_id' => $data['bank_account_id'],
                    'payment_date' => $data['payment_date'],
                    'amount' => round((float) $data['amount'], 4),
                    'payment_method' => $data['payment_method'],
                    'reference' => $data['reference'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $request->user()->id,
                ]);

                $this->accountingService->postInvoicePayment($payment, $request->user());

                AuditLog::record(
                    $request->user(),
                    'record_payment',
                    "Received payment {$payment->payment_number} ({$payment->amount}) for invoice {$invoice->invoice_number}",
                    $payment
                );
            });

            return back()->with('success', 'Payment recorded and posted to General Ledger.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function void(Request $request, Invoice $invoice): RedirectResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->accountingService->voidInvoice($invoice, $request->user(), $request->input('reason'));

            return back()->with('success', "Invoice {$invoice->invoice_number} has been voided and ledger entries reversed.");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
