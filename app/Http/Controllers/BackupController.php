<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function index(): View
    {
        $backupDir = storage_path('app/backups');
        $backups = [];

        if (File::isDirectory($backupDir)) {
            $files = File::files($backupDir);
            foreach ($files as $file) {
                $backups[] = [
                    'filename' => $file->getFilename(),
                    'size' => round($file->getSize() / 1024, 2), // in KB
                    'created_at' => date('Y-m-d H:i:s', $file->getMTime()),
                ];
            }

            usort($backups, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));
        }

        return view('backups.index', compact('backups'));
    }

    public function create(Request $request): RedirectResponse
    {
        Artisan::call('ais:backup', [
            '--user-id' => $request->user()->id,
        ]);

        return redirect()->route('backups.index')
            ->with('success', 'Database backup archive successfully created.');
    }

    public function download(string $filename): BinaryFileResponse|RedirectResponse
    {
        $clean = basename($filename);
        $path = storage_path("app/backups/{$clean}");

        if (! File::exists($path)) {
            return back()->with('error', 'Requested backup file does not exist.');
        }

        return response()->download($path);
    }

    public function destroy(Request $request, string $filename): RedirectResponse
    {
        $clean = basename($filename);
        $path = storage_path("app/backups/{$clean}");

        if (File::exists($path)) {
            File::delete($path);

            AuditLog::record(
                $request->user(),
                'delete_backup',
                "Deleted database backup archive: {$clean}"
            );

            return redirect()->route('backups.index')
                ->with('success', "Backup archive {$clean} deleted.");
        }

        return back()->with('error', 'Backup file not found.');
    }
}
