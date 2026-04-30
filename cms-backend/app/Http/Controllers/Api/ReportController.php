<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Export;
use App\Models\User;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Get daily attendance report.
     */
    public function dailyAttendance(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'classroom_id' => 'nullable|integer|exists:classrooms,id',
        ]);

        $query = Attendance::whereDate('date', $validated['date']);

        if ($request->has('classroom_id')) {
            $query->where('classroom_id', $request->classroom_id);
        }

        $records = $query->with(['user', 'classroom'])
            ->orderBy('status')
            ->get();

        return response()->json([
            'date' => $validated['date'],
            'total' => $records->count(),
            'present' => $records->where('status', 'Present')->count(),
            'absent' => $records->where('status', 'Absent')->count(),
            'late' => $records->where('status', 'Late')->count(),
            'excused' => $records->where('status', 'Excused')->count(),
            'records' => $records,
        ]);
    }

    /**
     * Get weekly attendance report.
     */
    public function weeklyAttendance(Request $request)
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

        $records = $query->with(['user', 'classroom'])->get();

        $grouped = $records->groupBy(function ($item) {
            return $item->user->id;
        })->map(function ($group) {
            return [
                'user_id' => $group[0]->user->id,
                'user_name' => $group[0]->user->name,
                'total' => $group->count(),
                'present' => $group->where('status', 'Present')->count(),
                'absent' => $group->where('status', 'Absent')->count(),
                'late' => $group->where('status', 'Late')->count(),
                'excused' => $group->where('status', 'Excused')->count(),
            ];
        });

        return response()->json([
            'period' => ['from' => $validated['date_from'], 'to' => $validated['date_to']],
            'total_records' => $records->count(),
            'summary' => $grouped->values(),
        ]);
    }

    /**
     * Get monthly attendance report.
     */
    public function monthlyAttendance(Request $request)
    {
        $validated = $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer',
            'classroom_id' => 'nullable|integer|exists:classrooms,id',
        ]);

        $startDate = date('Y-m-01', strtotime("{$validated['year']}-{$validated['month']}-01"));
        $endDate = date('Y-m-t', strtotime($startDate));

        $query = Attendance::whereBetween('date', [$startDate, $endDate]);

        if ($request->has('classroom_id')) {
            $query->where('classroom_id', $request->classroom_id);
        }

        $records = $query->with(['user', 'classroom'])->get();

        $grouped = $records->groupBy(function ($item) {
            return $item->user->id;
        })->map(function ($group) {
            return [
                'user_id' => $group[0]->user->id,
                'user_name' => $group[0]->user->name,
                'total' => $group->count(),
                'present' => $group->where('status', 'Present')->count(),
                'absent' => $group->where('status', 'Absent')->count(),
                'late' => $group->where('status', 'Late')->count(),
                'excused' => $group->where('status', 'Excused')->count(),
                'attendance_percentage' => round(($group->where('status', 'Present')->count() / $group->count()) * 100, 2),
            ];
        });

        return response()->json([
            'month' => $validated['month'],
            'year' => $validated['year'],
            'total_records' => $records->count(),
            'summary' => $grouped->values(),
        ]);
    }

    /**
     * Get classroom attendance report.
     */
    public function classroomReport(Request $request)
    {
        $validated = $request->validate([
            'classroom_id' => 'required|integer|exists:classrooms,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date',
        ]);

        $classroom = Classroom::find($validated['classroom_id']);

        $records = Attendance::where('classroom_id', $classroom->id)
            ->whereBetween('date', [$validated['date_from'], $validated['date_to']])
            ->with(['user', 'classroom'])
            ->get();

        $grouped = $records->groupBy('user_id')->map(function ($group) {
            return [
                'user_name' => $group[0]->user->name,
                'total_days' => $group->count(),
                'present' => $group->where('status', 'Present')->count(),
                'absent' => $group->where('status', 'Absent')->count(),
                'late' => $group->where('status', 'Late')->count(),
                'excused' => $group->where('status', 'Excused')->count(),
                'attendance_percentage' => round(($group->where('status', 'Present')->count() / $group->count()) * 100, 2),
            ];
        });

        return response()->json([
            'classroom' => $classroom->name,
            'period' => ['from' => $validated['date_from'], 'to' => $validated['date_to']],
            'total_records' => $records->count(),
            'summary' => $grouped->values(),
        ]);
    }

    /**
     * Get individual attendance report.
     */
    public function individualReport(Request $request, User $user)
    {
        $this->ensureCanViewUser($user);

        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date',
        ]);

        $records = Attendance::where('user_id', $user->id)
            ->whereBetween('date', [$validated['date_from'], $validated['date_to']])
            ->with(['classroom', 'recorder'])
            ->orderBy('date', 'desc')
            ->get();

        return response()->json([
            'user' => $user->name,
            'period' => ['from' => $validated['date_from'], 'to' => $validated['date_to']],
            'total_days' => $records->count(),
            'present' => $records->where('status', 'Present')->count(),
            'absent' => $records->where('status', 'Absent')->count(),
            'late' => $records->where('status', 'Late')->count(),
            'excused' => $records->where('status', 'Excused')->count(),
            'attendance_percentage' => $records->count() > 0 
                ? round(($records->where('status', 'Present')->count() / $records->count()) * 100, 2)
                : 0,
            'records' => $records,
        ]);
    }

    /**
     * Export attendance data as CSV.
     */
    public function exportCsv(Request $request)
    {
        $validated = $request->validate([
            'report_type' => 'required|in:daily,weekly,monthly,classroom,individual',
            'date' => 'nullable|date',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'classroom_id' => 'nullable|integer|exists:classrooms,id',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        // Build query based on report type
        $query = Attendance::query();
        $user = auth()->user();

        if ($user->isStudentEmployeeParticipant()) {
            $query->where('user_id', $user->id);
            $validated['report_type'] = 'individual';
            $validated['user_id'] = $user->id;
        }

        if ($validated['report_type'] === 'daily' && $validated['date']) {
            $query->whereDate('date', $validated['date']);
        } elseif ($validated['report_type'] === 'weekly' || $validated['report_type'] === 'monthly') {
            if ($validated['date_from'] && $validated['date_to']) {
                $query->whereBetween('date', [$validated['date_from'], $validated['date_to']]);
            }
        } elseif ($validated['report_type'] === 'classroom' && $validated['classroom_id']) {
            $query->where('classroom_id', $validated['classroom_id']);
            if ($validated['date_from'] && $validated['date_to']) {
                $query->whereBetween('date', [$validated['date_from'], $validated['date_to']]);
            }
        } elseif ($validated['report_type'] === 'individual' && $validated['user_id']) {
            $query->where('user_id', $validated['user_id']);
            if ($validated['date_from'] && $validated['date_to']) {
                $query->whereBetween('date', [$validated['date_from'], $validated['date_to']]);
            }
        }

        $records = $query->with(['user', 'classroom'])->get();

        // Log the export
        Export::create([
            'exported_by' => auth()->id(),
            'type' => $validated['report_type'],
            'format' => 'CSV',
            'file_path' => null,
            'filters' => $validated,
        ]);

        // Generate CSV content
        $csv = "Date,User,Classroom,Status,Time In,Time Out,Remarks\n";

        foreach ($records as $record) {
            $csv .= implode(',', [
                $record->date,
                $record->user->name,
                $record->classroom?->name ?? 'N/A',
                $record->status,
                $record->time_in ?? '',
                $record->time_out ?? '',
                str_replace(',', ';', $record->remarks ?? ''),
            ]) . "\n";
        }

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'attendance-report-' . now()->format('Y-m-d-His') . '.csv');
    }
}
