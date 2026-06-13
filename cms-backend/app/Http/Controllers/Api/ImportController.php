<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Import;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportController extends Controller
{
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
            'type' => 'required|in:users,attendance',
        ]);

        if ($validated['type'] === 'users' && ! $request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Only admins can import users.'], 403);
        }

        try {
            $filePath = $request->file('file')->store('imports', 'local');

            $import = Import::create([
                'uploaded_by' => auth()->id(),
                'file_name' => $request->file('file')->getClientOriginalName(),
                'type' => $validated['type'],
                'status' => 'pending',
                'total_rows' => 0,
            ]);

            $this->processImport($import, Storage::disk('local')->path($filePath));
            $this->audit('import.processed', $import, null, $import->fresh()->toArray());

            return response()->json([
                'message' => 'File uploaded and processed successfully.',
                'import' => $import->fresh(),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function show(Import $import)
    {
        return response()->json($import->load('uploadedBy'));
    }

    public function downloadTemplate($type = 'attendance')
    {
        $type = in_array($type, ['users', 'attendance']) ? $type : 'attendance';

        if ($type === 'users') {
            $headers = ['Name', 'Email', 'Password', 'Role', 'Phone', 'Status'];
        } else {
            $headers = ['User Email', 'Date', 'Status', 'Time In', 'Classroom ID'];
        }

        $csv = implode(',', $headers)."\n";

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, "import-template-{$type}.csv");
    }

    private function processImport(Import $import, string $filePath): void
    {
        try {
            $import->update(['status' => 'processing']);

            $rows = $this->readRows($filePath);
            $header = array_map([$this, 'key'], array_shift($rows) ?: []);
            $successCount = 0;
            $failCount = 0;
            $errors = [];

            DB::beginTransaction();

            foreach ($rows as $lineNum => $row) {
                try {
                    $data = array_combine($header, $row);

                    if (! $data) {
                        throw new \RuntimeException('Invalid row structure.');
                    }

                    $import->type === 'users'
                        ? $this->importUser($data)
                        : $this->importAttendance($data);

                    $successCount++;
                } catch (\Throwable $e) {
                    $failCount++;
                    $errors[] = 'Line '.($lineNum + 2).': '.$e->getMessage();
                }
            }

            DB::commit();

            $import->update([
                'status' => 'completed',
                'total_rows' => count($rows),
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

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password'] ?? 'default123'),
            'role' => $data['role'],
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);

        DB::afterCommit(fn () => $this->sendVerificationNotification($user));
    }

    private function importAttendance(array $data): void
    {
        foreach (['user_email', 'date', 'status', 'classroom_id'] as $field) {
            if (empty($data[$field])) {
                throw new \RuntimeException("Missing required field: {$field}");
            }
        }

        $user = User::where('email', $data['user_email'])->first();

        if (! $user) {
            throw new \RuntimeException("User not found: {$data['user_email']}");
        }

        $classroom = Classroom::find($data['classroom_id']);

        if (! $classroom) {
            throw new \RuntimeException("Classroom not found: {$data['classroom_id']}");
        }

        $viewer = auth()->user();

        if ($viewer->isStaffTeacherSupervisor()) {
            $allowed = (int) $classroom->teacher_id === (int) $viewer->id
                && (int) $user->classroom_id === (int) $classroom->id;

            if (! $allowed) {
                throw new \RuntimeException("You cannot import attendance for {$data['user_email']}");
            }
        }

        $existing = Attendance::where('user_id', $user->id)
            ->whereDate('date', $data['date'])
            ->where('classroom_id', $data['classroom_id'])
            ->first();

        if ($existing) {
            throw new \RuntimeException("Attendance already exists for {$data['user_email']} on {$data['date']}");
        }

        Attendance::create([
            'user_id' => $user->id,
            'classroom_id' => $classroom->id,
            'date' => $data['date'],
            'time_in' => $data['time_in'] ?? null,
            'status' => $data['status'],
            'recorded_by' => auth()->id(),
        ]);
    }

    private function readRows(string $filePath): array
    {
        if (in_array(strtolower(pathinfo($filePath, PATHINFO_EXTENSION)), ['xlsx', 'xls'], true)) {
            return IOFactory::load($filePath)->getActiveSheet()->toArray(null, true, true, false);
        }

        return array_map('str_getcsv', file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    }

    private function key(mixed $value): string
    {
        return str_replace(' ', '_', strtolower(trim((string) $value)));
    }

    private function sendVerificationNotification(User $user): bool
    {
        $code = $user->startEmailVerification();

        if (! $code) {
            return false;
        }

        return app(NotificationController::class)->sendVerificationCode($user->fresh(), $code);
    }
}
