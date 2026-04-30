<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Classroom;
use Illuminate\Http\Request;

class ClassroomController extends Controller
{
    /**
     * Display a listing of classrooms.
     */
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

    /**
     * Store a newly created classroom.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'teacher_id' => 'nullable|integer|exists:users,id',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        $classroom = Classroom::create($validated);

        return response()->json($classroom, 201);
    }

    /**
     * Display a specific classroom.
     */
    public function show(Classroom $classroom)
    {
        $this->ensureCanAccessClassroom($classroom);

        return response()->json($classroom->load(['teacher', 'users', 'attendance']));
    }

    /**
     * Update a classroom.
     */
    public function update(Request $request, Classroom $classroom)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'teacher_id' => 'nullable|integer|exists:users,id',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $classroom->update($validated);

        return response()->json($classroom);
    }

    /**
     * Delete a classroom.
     */
    public function destroy(Classroom $classroom)
    {
        $classroom->update(['status' => 'inactive']);

        return response()->json(['message' => 'Classroom deactivated successfully']);
    }

    /**
     * Get students in a classroom.
     */
    public function getStudents(Classroom $classroom)
    {
        $this->ensureCanAccessClassroom($classroom);

        $students = $classroom->users()->where('role', 'student_employee_participant')->get();

        return response()->json($students);
    }

    /**
     * Assign multiple users to a classroom.
     */
    public function assignUsers(Request $request, Classroom $classroom)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        foreach ($validated['user_ids'] as $userId) {
            $user = \App\Models\User::find($userId);
            if ($user && $user->isStudentEmployeeParticipant()) {
                $user->update(['classroom_id' => $classroom->id]);
            }
        }

        return response()->json(['message' => 'Users assigned successfully']);
    }
}
