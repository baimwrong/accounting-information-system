<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
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

        return view('payments.index', compact('payments'));
    }

    public function show(Payment $payment): View
    {
        $payment->load(['contact', 'bankAccount', 'invoice', 'bill', 'journalEntry.items.account', 'creator']);

        return view('payments.show', compact('payment'));
    }
}
