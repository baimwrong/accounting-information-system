<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $query = Product::with(['incomeAccount', 'expenseAccount'])->orderBy('name');

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

        $products = $query->paginate(20)->withQueryString();
        $accounts = Account::where('is_active', true)->orderBy('code')->get();

        return view('products.index', compact('products', 'accounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:products,code'],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:goods,service'],
            'income_account_id' => ['required', 'exists:accounts,id'],
            'expense_account_id' => ['required', 'exists:accounts,id'],
            'asset_account_id' => ['nullable', 'exists:accounts,id'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'quantity_on_hand' => ['nullable', 'numeric'],
            'description' => ['nullable', 'string'],
        ]);

        $product = Product::create($data);

        AuditLog::record(
            $request->user(),
            'create_product',
            "Created item {$product->code} - {$product->name}",
            $product
        );

        return redirect()->route('products.index')->with('success', "Item {$product->code} created successfully.");
    }

    public function edit(Product $product): View
    {
        $accounts = Account::where('is_active', true)->orderBy('code')->get();

        return view('products.edit', compact('product', 'accounts'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:products,code,'.$product->id],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:goods,service'],
            'income_account_id' => ['required', 'exists:accounts,id'],
            'expense_account_id' => ['required', 'exists:accounts,id'],
            'asset_account_id' => ['nullable', 'exists:accounts,id'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'quantity_on_hand' => ['nullable', 'numeric'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $product->update($data);

        AuditLog::record(
            $request->user(),
            'update_product',
            "Updated item {$product->code} - {$product->name}",
            $product
        );

        return redirect()->route('products.index')->with('success', "Item {$product->code} updated successfully.");
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        if ($product->invoiceItems()->exists() || $product->billItems()->exists()) {
            return back()->with('error', 'Cannot delete item because it has recorded invoice or bill transaction history. You can deactivate it instead.');
        }

        $code = $product->code;
        $name = $product->name;
        $product->delete();

        AuditLog::record($request->user(), 'delete_product', "Deleted item {$code} - {$name}");

        return redirect()->route('products.index')->with('success', "Item {$code} deleted.");
    }
}
