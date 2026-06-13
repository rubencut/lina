<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    private const STATUSES = ['Present', 'Absent', 'Late', 'Excused'];
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'classroom_id' => 'nullable|integer|exists:classrooms,id',
            'attendance_session_id' => 'nullable|integer|exists:attendance_sessions,id',
            'date' => 'required|date',
            'time_in' => 'nullable|date_format:H:i',
            'status' => ['required', 'string', 'in:' . implode(',', self::STATUSES)],
        ]);

        $existing = Attendance::where('user_id', $validated['user_id'])
            ->where('date', $validated['date']);

        if ($validated['classroom_id'] ?? null) {
            $existing->where('classroom_id', $validated['classroom_id']);
        }

        if ($validated['attendance_session_id'] ?? null) {
            $existing->where('attendance_session_id', $validated['attendance_session_id']);
        } else {
            $existing->whereNull('attendance_session_id');
        }

        if ($existing->exists()) {
            return response()->json(['message' => 'Attendance already recorded for this user on this date'], 409);
        }

        $validated['recorded_by'] = auth()->id();

        $record = Attendance::create($validated);
        $this->audit('attendance.created', $record, null, $record->toArray());

        return response()->json($record->load(['user', 'classroom', 'recorder']), 201);
    }

    public function markByQr(Request $request)
    {
        $validated = $request->validate([
            'qr_code' => 'required|string',
            'date' => 'nullable|date',
            'time_in' => 'nullable|date_format:H:i',
        ]);

        $teacher = $request->user();
        $student = User::with('classroom')
            ->where('qr_code', trim($validated['qr_code']))
            ->where('status', 'active')
            ->first();

        if (!$student) {
            return response()->json(['message' => 'QR code was not found.'], 404);
        }

        if (!$student->isStudentEmployeeParticipant()) {
            return response()->json(['message' => 'This QR code does not belong to a student.'], 422);
        }

        if (
            !$student->classroom
            || $student->classroom->status !== 'active'
            || (int) $student->classroom->teacher_id !== (int) $teacher->id
        ) {
            return response()->json(['message' => 'This student is not assigned to your active classroom.'], 403);
        }

        $date = $validated['date'] ?? now()->toDateString();
        $timeIn = $validated['time_in'] ?? now()->format('H:i');

        $record = Attendance::where('user_id', $student->id)
            ->where('classroom_id', $student->classroom_id)
            ->whereDate('date', $date)
            ->whereNull('attendance_session_id')
            ->first();

        if ($record) {
            if ($record->status !== 'Present' || !$record->time_in) {
                $old = $record->only(['status', 'time_in', 'recorded_by']);
                $record->update([
                    'status' => 'Present',
                    'time_in' => $timeIn,
                    'recorded_by' => $teacher->id,
                ]);
                $this->audit('attendance.updated_by_qr', $record, $old, $record->fresh()->toArray());
            }

            return response()->json([
                'message' => "{$student->name} is already marked present for {$date}.",
                'already_recorded' => true,
                'attendance' => $record->fresh(['user', 'classroom', 'recorder']),
            ]);
        }

        $record = Attendance::create([
            'user_id' => $student->id,
            'classroom_id' => $student->classroom_id,
            'date' => $date,
            'time_in' => $timeIn,
            'status' => 'Present',
            'recorded_by' => $teacher->id,
        ]);

        $this->audit('attendance.created_by_qr', $record, null, $record->toArray());

        return response()->json([
            'message' => "{$student->name} marked present.",
            'already_recorded' => false,
            'attendance' => $record->load(['user', 'classroom', 'recorder']),
        ], 201);
    }

    public function show(Attendance $record)
    {
        $this->ensureCanAccessAttendance($record);

        return response()->json($record->load(['user', 'classroom', 'recorder']));
    }

    public function update(Request $request, Attendance $record)
    {
        $this->ensureCanAccessAttendance($record, true);
        $old = $record->only(['status', 'time_in']);

        $validated = $request->validate([
            'status' => 'sometimes|in:Present,Absent,Late,Excused',
            'time_in' => 'nullable|date_format:H:i',
        ]);

        $record->update($validated);
        $this->audit('attendance.updated', $record, $old, $record->fresh()->toArray());

        return response()->json($record);
    }

    public function destroy(Attendance $record)
    {
        $this->ensureCanAccessAttendance($record, true);
        $old = $record->toArray();

        $record->delete();
        $this->audit('attendance.deleted', $record, $old);

        return response()->json(['message' => 'Attendance record deleted successfully']);
    }

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
            'present' => (clone $query)->where('status', self::STATUSES[0])->count(),
            'absent' => (clone $query)->where('status', self::STATUSES[1])->count(),
            'late' => (clone $query)->where('status', self::STATUSES[2])->count(),
            'excused' => (clone $query)->where('status', self::STATUSES[3])->count(),
        ];

        return response()->json($summary);
    }
}
