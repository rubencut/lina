<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $user = request()->user();

        return response()->json([
            'message' => 'Classroom dashboard loaded successfully.',
            'role' => $user->role,
            'allowed_pages' => $this->allowedPages($user->role),
        ]);
    }

    public function summary()
    {
        $today = now()->toDateString();
        $attendance = Attendance::with(['user', 'classroom'])
            ->when(request()->user()->isStudentEmployeeParticipant(), fn ($query) => $query->where('user_id', request()->user()->id))
            ->latest();

        return response()->json([
            'stats' => [
                ['label' => 'Registered Users', 'value' => User::count()],
                ['label' => 'Classrooms', 'value' => Classroom::count()],
                ['label' => 'Present Today', 'value' => Attendance::whereDate('date', $today)->where('status', 'Present')->count()],
                ['label' => 'Absent Today', 'value' => Attendance::whereDate('date', $today)->where('status', 'Absent')->count()],
            ],
            'recent_attendance' => $attendance->limit(8)->get()->map(fn ($record) => [
                'date' => optional($record->date)->toDateString(),
                'user' => $record->user?->name,
                'classroom' => $record->classroom?->name,
                'status' => $record->status,
                'time_in' => $record->time_in,
            ]),
        ]);
    }

    private function allowedPages(string $role): array
    {
        return match ($role) {
            'super_admin' => ['dashboard', 'users', 'classrooms', 'reports', 'qr'],
            'staff_teacher_supervisor' => ['classrooms', 'reports', 'qr'],
            'student_employee_participant' => ['classrooms', 'reports'],
            default => [],
        };
    }
}
