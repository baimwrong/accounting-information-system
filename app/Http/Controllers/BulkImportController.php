<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Services\BulkImportService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BulkImportController extends Controller
{
    public function __construct(
        protected BulkImportService $bulkImportService
    ) {}

    public function index(): View
    {
        $batches = ImportBatch::with('user')->latest()->paginate(15);

        return view('imports.index', compact('batches'));
    }

    public function importAccounts(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        try {
            $batch = $this->bulkImportService->importAccounts($request->file('csv_file'), $request->user());

            if ($batch->failed_rows > 0) {
                return redirect()->route('imports.index')
                    ->with('warning', "Chart of Accounts import finished: {$batch->successful_rows} successful, {$batch->failed_rows} errors.");
            }

            return redirect()->route('imports.index')
                ->with('success', "Chart of Accounts import completed successfully ({$batch->successful_rows} accounts imported).");
        } catch (Exception $e) {
            return back()->with('error', 'Import error: '.$e->getMessage());
        }
    }

    public function importContacts(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        try {
            $batch = $this->bulkImportService->importContacts($request->file('csv_file'), $request->user());

            if ($batch->failed_rows > 0) {
                return redirect()->route('imports.index')
                    ->with('warning', "Contacts import finished: {$batch->successful_rows} successful, {$batch->failed_rows} errors.");
            }

            return redirect()->route('imports.index')
                ->with('success', "Contacts import completed successfully ({$batch->successful_rows} contacts imported).");
        } catch (Exception $e) {
            return back()->with('error', 'Import error: '.$e->getMessage());
        }
    }

    public function importProducts(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        try {
            $batch = $this->bulkImportService->importProducts($request->file('csv_file'), $request->user());

            if ($batch->failed_rows > 0) {
                return redirect()->route('imports.index')
                    ->with('warning', "Products import finished: {$batch->successful_rows} successful, {$batch->failed_rows} errors.");
            }

            return redirect()->route('imports.index')
                ->with('success', "Products import completed successfully ({$batch->successful_rows} items imported).");
        } catch (Exception $e) {
            return back()->with('error', 'Import error: '.$e->getMessage());
        }
    }

    public function importJournalEntries(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        try {
            $batch = $this->bulkImportService->importJournalEntries($request->file('csv_file'), $request->user());

            if ($batch->failed_rows > 0) {
                return redirect()->route('imports.index')
                    ->with('warning', "Journal entries import finished: {$batch->successful_rows} successful, {$batch->failed_rows} errors.");
            }

            return redirect()->route('imports.index')
                ->with('success', "Journal entries imported and posted successfully ({$batch->successful_rows} entries imported).");
        } catch (Exception $e) {
            return back()->with('error', 'Import error: '.$e->getMessage());
        }
    }

    public function downloadTemplate(string $type): StreamedResponse
    {
        return $this->bulkImportService->downloadSampleTemplate($type);
    }

    public function showBatch(ImportBatch $batch): JsonResponse
    {
        return response()->json([
            'id' => $batch->id,
            'type' => $batch->type,
            'file_name' => $batch->file_name,
            'status' => $batch->status,
            'total_rows' => $batch->total_rows,
            'successful_rows' => $batch->successful_rows,
            'failed_rows' => $batch->failed_rows,
            'error_summary' => $batch->error_summary,
            'created_at' => $batch->created_at->toDateTimeString(),
        ]);
    }
}
