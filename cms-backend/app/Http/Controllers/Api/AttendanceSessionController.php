<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use Illuminate\Http\Request;

class AttendanceSessionController extends Controller
{
    public function index(Request $request)
    {
        $query = AttendanceSession::with(['classroom', 'creator'])->latest('date');
        $user = $request->user();

        if ($user->isStaffTeacherSupervisor()) {
            $query->whereHas('classroom', fn ($classroom) => $classroom->where('teacher_id', $user->id));
        }

        if ($request->filled('classroom_id')) {
            $query->where('classroom_id', $request->classroom_id);
        }

        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }

        return response()->json($query->paginate($request->per_page ?? 15));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'classroom_id' => 'required|integer|exists:classrooms,id',
            'title' => 'nullable|string|max:255',
            'date' => 'required|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
        ]);

        $session = AttendanceSession::create($validated + ['created_by' => $request->user()->id]);

        $this->audit('attendance_session.created', $session, null, $session->toArray());

        return response()->json($session->load(['classroom', 'creator']), 201);
    }

    public function update(Request $request, AttendanceSession $attendanceSession)
    {
        $old = $attendanceSession->only(['classroom_id', 'title', 'date', 'start_time', 'end_time']);

        $validated = $request->validate([
            'classroom_id' => 'sometimes|integer|exists:classrooms,id',
            'title' => 'nullable|string|max:255',
            'date' => 'sometimes|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
        ]);

        $attendanceSession->update($validated);
        $this->audit('attendance_session.updated', $attendanceSession, $old, $attendanceSession->fresh()->toArray());

        return response()->json($attendanceSession->fresh(['classroom', 'creator']));
    }

    public function destroy(AttendanceSession $attendanceSession)
    {
        $old = $attendanceSession->toArray();
        $attendanceSession->delete();

        $this->audit('attendance_session.deleted', $attendanceSession, $old);

        return response()->json(['message' => 'Attendance session deleted successfully.']);
    }
}
