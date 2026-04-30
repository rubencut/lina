<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Attendance;
use App\Models\Import;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ImportController extends Controller
{
    /**
     * List all imports.
     */
    public function index(Request $request)
    {
        $imports = Import::where('uploaded_by', auth()->id())
            ->orWhere(function ($query) {
                $query->where(function ($q) {
                    $q->whereHas('uploadedBy', function ($q) {
                        $q->where('role', 'super_admin');
                    });
                });
            })
            ->paginate($request->per_page ?? 15);

        return response()->json($imports);
    }

    /**
     * Upload and process import file.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
            'type' => 'required|in:users,attendance',
        ]);

        try {
            // Store the file
            $filePath = $request->file('file')->store('imports', 'local');

            // Create import record
            $import = Import::create([
                'uploaded_by' => auth()->id(),
                'file_name' => $request->file('file')->getClientOriginalName(),
                'type' => $validated['type'],
                'status' => 'pending',
                'total_rows' => 0,
            ]);

            $this->processImport($import, storage_path('app/' . $filePath));

            return response()->json([
                'message' => 'File uploaded and processed successfully.',
                'import' => $import->fresh(),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get import details.
     */
    public function show(Import $import)
    {
        return response()->json($import->load('uploadedBy'));
    }

    /**
     * Download import template.
     */
    public function downloadTemplate($type = 'attendance')
    {
        $type = in_array($type, ['users', 'attendance']) ? $type : 'attendance';

        if ($type === 'users') {
            $headers = ['Name', 'Email', 'Password', 'Role', 'Phone', 'Status'];
        } else {
            $headers = ['User Email', 'Date', 'Status', 'Time In', 'Time Out', 'Remarks', 'Classroom ID'];
        }

        $csv = implode(',', $headers) . "\n";

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, "import-template-{$type}.csv");
    }

    private function processImport(Import $import, string $filePath): void
    {
        try {
            $import->update(['status' => 'processing']);

            $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $header = array_map('strtolower', str_getcsv(array_shift($lines) ?: ''));
            $successCount = 0;
            $failCount = 0;
            $errors = [];

            DB::beginTransaction();

            foreach ($lines as $lineNum => $line) {
                $row = str_getcsv($line);

                try {
                    $data = array_combine($header, $row);

                    if (!$data) {
                        throw new \RuntimeException('Invalid row structure.');
                    }

                    $import->type === 'users'
                        ? $this->importUser($data)
                        : $this->importAttendance($data);

                    $successCount++;
                } catch (\Throwable $e) {
                    $failCount++;
                    $errors[] = 'Line ' . ($lineNum + 2) . ': ' . $e->getMessage();
                }
            }

            DB::commit();

            $import->update([
                'status' => 'completed',
                'total_rows' => count($lines),
                'successful_rows' => $successCount,
                'failed_rows' => $failCount,
                'error_log' => $errors,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            $import->update([
                'status' => 'failed',
                'error_log' => [$e->getMessage()],
            ]);

            throw $e;
        }
    }

    private function importUser(array $data): void
    {
        foreach (['name', 'email', 'role'] as $field) {
            if (empty($data[$field])) {
                throw new \RuntimeException("Missing required field: {$field}");
            }
        }

        if (User::where('email', $data['email'])->exists()) {
            throw new \RuntimeException("User with email {$data['email']} already exists");
        }

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password'] ?? 'default123'),
            'role' => $data['role'],
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);
    }

    private function importAttendance(array $data): void
    {
        foreach (['user_email', 'date', 'status'] as $field) {
            if (empty($data[$field])) {
                throw new \RuntimeException("Missing required field: {$field}");
            }
        }

        $user = User::where('email', $data['user_email'])->first();

        if (!$user) {
            throw new \RuntimeException("User not found: {$data['user_email']}");
        }

        $existing = Attendance::where('user_id', $user->id)
            ->whereDate('date', $data['date'])
            ->first();

        if ($existing) {
            throw new \RuntimeException("Attendance already exists for {$data['user_email']} on {$data['date']}");
        }

        Attendance::create([
            'user_id' => $user->id,
            'classroom_id' => $data['classroom_id'] ?? null,
            'date' => $data['date'],
            'time_in' => $data['time_in'] ?? null,
            'time_out' => $data['time_out'] ?? null,
            'status' => $data['status'],
            'remarks' => $data['remarks'] ?? null,
            'recorded_by' => auth()->id(),
        ]);
    }
}
