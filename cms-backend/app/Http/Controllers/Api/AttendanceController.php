<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    /**
     * Display a listing of attendance records.
     */
    public function index(Request $request)
    {
        $query = Attendance::query();
        $user = auth()->user();

        if ($user->isStudentEmployeeParticipant()) {
            $query->where('user_id', $user->id);
        } elseif ($user->isStaffTeacherSupervisor()) {
            $query->where(function ($q) use ($user) {
                $q->where('recorded_by', $user->id)
                    ->orWhereHas('classroom', fn ($classroom) => $classroom->where('teacher_id', $user->id));
            });
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('classroom_id')) {
            $query->where('classroom_id', $request->classroom_id);
        }

        if ($request->has('date')) {
            $query->whereDate('date', $request->date);
        }

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->whereBetween('date', [$request->date_from, $request->date_to]);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $records = $query->with(['user', 'classroom', 'recorder'])
            ->orderBy('date', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json($records);
    }

    /**
     * Store a new attendance record.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'classroom_id' => 'nullable|integer|exists:classrooms,id',
            'date' => 'required|date',
            'time_in' => 'nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i',
            'status' => 'required|in:Present,Absent,Late,Excused',
            'remarks' => 'nullable|string',
        ]);

        // Check for duplicate attendance (same user, classroom, date)
        $existing = Attendance::where('user_id', $validated['user_id'])
            ->where('date', $validated['date']);

        if ($validated['classroom_id']) {
            $existing->where('classroom_id', $validated['classroom_id']);
        }

        if ($existing->exists()) {
            return response()->json(['message' => 'Attendance already recorded for this user on this date'], 409);
        }

        $validated['recorded_by'] = auth()->id();

        $record = Attendance::create($validated);

        return response()->json($record->load(['user', 'classroom', 'recorder']), 201);
    }

    /**
     * Display a specific attendance record.
     */
    public function show(Attendance $record)
    {
        $this->ensureCanAccessAttendance($record);

        return response()->json($record->load(['user', 'classroom', 'recorder']));
    }

    /**
     * Update an attendance record.
     */
    public function update(Request $request, Attendance $record)
    {
        $this->ensureCanAccessAttendance($record, true);

        $validated = $request->validate([
            'status' => 'sometimes|in:Present,Absent,Late,Excused',
            'remarks' => 'nullable|string',
            'time_in' => 'nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i',
        ]);

        $record->update($validated);

        return response()->json($record);
    }

    /**
     * Delete an attendance record.
     */
    public function destroy(Attendance $record)
    {
        $this->ensureCanAccessAttendance($record, true);

        $record->delete();

        return response()->json(['message' => 'Attendance record deleted successfully']);
    }

    /**
     * Mark attendance with QR code.
     */
    public function markByQrCode(Request $request)
    {
        $validated = $request->validate([
            'qr_code' => 'required|string',
            'classroom_id' => 'required|integer|exists:classrooms,id',
        ]);

        $user = User::where('qr_code', $validated['qr_code'])->first();

        if (!$user) {
            return response()->json(['message' => 'Invalid QR code'], 404);
        }

        // Check for duplicate
        $existing = Attendance::where('user_id', $user->id)
            ->where('classroom_id', $validated['classroom_id'])
            ->whereDate('date', now())
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Attendance already marked for this session'], 409);
        }

        $record = Attendance::create([
            'user_id' => $user->id,
            'classroom_id' => $validated['classroom_id'],
            'date' => now()->toDateString(),
            'time_in' => now()->toTimeString(),
            'status' => 'Present',
            'recorded_by' => auth()->id(),
        ]);

        return response()->json($record->load(['user', 'classroom']), 201);
    }

    /**
     * Get personal attendance history.
     */
    public function getPersonalHistory(Request $request)
    {
        $user = auth()->user();

        $query = Attendance::where('user_id', $user->id);

        if ($request->has('date_from') && $request->has('date_to')) {
            $query->whereBetween('date', [$request->date_from, $request->date_to]);
        }

        if ($request->has('classroom_id')) {
            $query->where('classroom_id', $request->classroom_id);
        }

        $records = $query->orderBy('date', 'desc')->paginate($request->per_page ?? 15);

        return response()->json($records);
    }

    /**
     * Get attendance summary.
     */
    public function getSummary(Request $request)
    {
        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date',
            'classroom_id' => 'nullable|integer|exists:classrooms,id',
        ]);

        $query = Attendance::whereBetween('date', [$validated['date_from'], $validated['date_to']]);

        if ($request->has('classroom_id')) {
            $query->where('classroom_id', $request->classroom_id);
        }

        $summary = [
            'present' => (clone $query)->where('status', 'Present')->count(),
            'absent' => (clone $query)->where('status', 'Absent')->count(),
            'late' => (clone $query)->where('status', 'Late')->count(),
            'excused' => (clone $query)->where('status', 'Excused')->count(),
        ];

        return response()->json($summary);
    }
}
