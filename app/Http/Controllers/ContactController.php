<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function index(Request $request): View
    {
        $query = Contact::query()->orderBy('name');

        if ($request->filled('type')) {
            $type = $request->input('type');
            if ($type === 'customer') {
                $query->whereIn('type', ['customer', 'both']);
            } elseif ($type === 'vendor') {
                $query->whereIn('type', ['vendor', 'both']);
            }
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('company_name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        $contacts = $query->paginate(20)->withQueryString();

        return view('contacts.index', compact('contacts'));
    }

    public function create(): View
    {
        return view('contacts.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:customer,vendor,both'],
            'name' => ['required', 'string', 'max:150'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'opening_balance' => ['nullable', 'numeric'],
        ]);

        $contact = Contact::create($data);

        AuditLog::record(
            $request->user(),
            'create_contact',
            "Created contact {$contact->name} ({$contact->type})",
            $contact
        );

        return redirect()->route('contacts.index')->with('success', "Contact {$contact->name} created successfully.");
    }

    public function show(Contact $contact): View
    {
        $contact->load([
            'invoices' => fn ($q) => $q->latest()->take(10),
            'bills' => fn ($q) => $q->latest()->take(10),
            'payments' => fn ($q) => $q->latest()->take(10),
        ]);

        return view('contacts.show', compact('contact'));
    }

    public function edit(Contact $contact): View
    {
        return view('contacts.edit', compact('contact'));
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:customer,vendor,both'],
            'name' => ['required', 'string', 'max:150'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'opening_balance' => ['nullable', 'numeric'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $contact->update($data);

        AuditLog::record(
            $request->user(),
            'update_contact',
            "Updated contact {$contact->name}",
            $contact
        );

        return redirect()->route('contacts.show', $contact)->with('success', "Contact {$contact->name} updated successfully.");
    }

    public function destroy(Request $request, Contact $contact): RedirectResponse
    {
        if ($contact->invoices()->exists() || $contact->bills()->exists() || $contact->payments()->exists()) {
            return back()->with('error', 'Cannot delete contact with existing transactions. Deactivate the contact instead.');
        }

        $name = $contact->name;
        $contact->delete();

        AuditLog::record($request->user(), 'delete_contact', "Deleted contact {$name}");

        return redirect()->route('contacts.index')->with('success', "Contact {$name} deleted.");
    }
}
