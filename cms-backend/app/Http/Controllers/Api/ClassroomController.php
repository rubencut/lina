<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Http\Request;

class ClassroomController extends Controller
{
    public function index(Request $request)
    {
        $query = Classroom::query();
        $user = auth()->user();

        if ($user->isStaffTeacherSupervisor()) {
            $query->where('teacher_id', $user->id);
        } elseif ($user->isStudentEmployeeParticipant()) {
            $query->where('id', $user->classroom_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $classrooms = $query->with('teacher')
            ->paginate($request->per_page ?? 15);

        return response()->json($classrooms);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'teacher_id' => 'nullable|integer|exists:users,id',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        if ($user->isStaffTeacherSupervisor()) {
            $validated['teacher_id'] = $user->id;
            $validated['status'] = 'active';
        }

        $classroom = Classroom::create($validated);
        $this->audit('classroom.created', $classroom, null, $classroom->toArray());

        return response()->json($classroom, 201);
    }

    public function show(Classroom $classroom)
    {
        $this->ensureCanAccessClassroom($classroom);

        return response()->json($classroom->load(['teacher', 'users', 'attendance']));
    }

    public function update(Request $request, Classroom $classroom)
    {
        $this->ensureCanAccessClassroom($classroom);

        $old = $classroom->only(['name', 'teacher_id', 'description', 'status']);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'teacher_id' => 'nullable|integer|exists:users,id',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
        ]);

        if ($request->user()->isStaffTeacherSupervisor()) {
            unset($validated['teacher_id'], $validated['status']);
        }

        $classroom->update($validated);
        $this->audit('classroom.updated', $classroom, $old, $classroom->fresh()->toArray());

        return response()->json($classroom);
    }

    public function destroy(Classroom $classroom)
    {
        $old = $classroom->only(['status']);
        $classroom->update(['status' => 'inactive']);
        $this->audit('classroom.deactivated', $classroom, $old, ['status' => 'inactive']);

        return response()->json(['message' => 'Classroom deactivated successfully']);
    }

    public function getStudents(Classroom $classroom)
    {
        $this->ensureCanAccessClassroom($classroom);

        $students = $classroom->users()
            ->where('role', 'student_employee_participant')
            ->get()
            ->map(function (User $student) {
                $student->qr_image = $student->qr_code ? QrCodeController::dataUrlFor($student) : null;

                return $student;
            });

        return response()->json($students);
    }

    public function assignUsers(Request $request, Classroom $classroom)
    {
        $this->ensureCanAccessClassroom($classroom);

        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        foreach ($validated['user_ids'] as $userId) {
            $user = User::find($userId);
            if ($user && $user->isStudentEmployeeParticipant()) {
                $old = $user->only(['classroom_id']);
                $user->update(['classroom_id' => $classroom->id]);
                $this->audit('user.assigned_to_classroom', $user, $old, ['classroom_id' => $classroom->id]);
            }
        }

        return response()->json(['message' => 'Users assigned successfully']);
    }

    public function attendance(Request $request, Classroom $classroom)
    {
        $this->ensureCanAccessClassroom($classroom);

        $query = Attendance::where('classroom_id', $classroom->id)
            ->with(['user', 'recorder'])
            ->latest('date');

        if ($request->user()->isStudentEmployeeParticipant()) {
            $query->where('user_id', $request->user()->id);
        }

        if ($request->boolean('draft')) {
            $query->whereNull('attendance_session_id');
        }

        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }

        return response()->json($query->paginate($request->per_page ?? 50));
    }

    public function submitAttendance(Request $request, Classroom $classroom)
    {
        $this->ensureCanAccessClassroom($classroom);

        $date = $request->validate([
            'date' => 'nullable|date',
        ])['date'] ?? now()->toDateString();

        $studentCount = $classroom->users()
            ->where('role', 'student_employee_participant')
            ->count();

        $records = Attendance::where('classroom_id', $classroom->id)
            ->whereDate('date', $date)
            ->whereNull('attendance_session_id')
            ->get();

        if ($records->isEmpty()) {
            return response()->json(['message' => 'No attendance records to submit for this date.'], 422);
        }

        if ($studentCount > 0 && $records->count() < $studentCount) {
            return response()->json(['message' => 'Mark all students before submitting attendance.'], 422);
        }

        $startTime = $records->pluck('time_in')
            ->filter()
            ->map(fn ($time) => is_string($time) ? substr($time, 0, 5) : $time->format('H:i'))
            ->min();

        $session = AttendanceSession::create([
            'classroom_id' => $classroom->id,
            'title' => 'Daily attendance',
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => now()->format('H:i'),
            'created_by' => auth()->id(),
            'submitted_at' => now(),
            'submitted_by' => auth()->id(),
        ]);

        Attendance::whereIn('id', $records->pluck('id'))->update([
            'attendance_session_id' => $session->id,
        ]);

        $this->audit('attendance.submitted', $session, null, [
            'classroom_id' => $classroom->id,
            'date' => $date,
            'records' => $records->count(),
        ]);

        return response()->json([
            'message' => 'Attendance submitted successfully.',
            'session' => $session->fresh(['classroom', 'creator', 'submitter']),
            'records' => $records->count(),
        ]);
    }
}
