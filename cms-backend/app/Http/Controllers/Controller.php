<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\User;

abstract class Controller
{
    protected function ensureCanViewUser(User $user): void
    {
        $viewer = auth()->user();

        if (!$viewer || (!$viewer->isSuperAdmin() && $viewer->id !== $user->id)) {
            if ($viewer?->isStaffTeacherSupervisor() && (int) $user->classroom?->teacher_id === (int) $viewer->id) {
                return;
            }

            abort(403, 'You are not allowed to view this user.');
        }
    }

    protected function ensureCanAccessClassroom(Classroom $classroom): void
    {
        $viewer = auth()->user();

        if (!$viewer) {
            abort(403);
        }

        if ($viewer->isSuperAdmin()) {
            return;
        }

        if ($viewer->isStaffTeacherSupervisor() && (int) $classroom->teacher_id === (int) $viewer->id) {
            return;
        }

        if ((int) $viewer->classroom_id === (int) $classroom->id) {
            return;
        }

        abort(403, 'You are not allowed to access this classroom.');
    }

    protected function ensureCanAccessAttendance(Attendance $attendance, bool $write = false): void
    {
        $viewer = auth()->user();

        if (!$viewer) {
            abort(403);
        }

        if ($viewer->isSuperAdmin()) {
            return;
        }

        if ($viewer->isStaffTeacherSupervisor()) {
            $ownsClassroom = $attendance->classroom
                && (int) $attendance->classroom->teacher_id === (int) $viewer->id;

            if ($ownsClassroom || (int) $attendance->recorded_by === (int) $viewer->id) {
                return;
            }
        }

        if (!$write && (int) $attendance->user_id === (int) $viewer->id) {
            return;
        }

        abort(403, 'You are not allowed to access this attendance record.');
    }
}
