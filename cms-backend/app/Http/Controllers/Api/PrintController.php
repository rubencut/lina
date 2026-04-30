<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Attendance;
use App\Models\Classroom;
use Illuminate\Http\Request;

class PrintController extends Controller
{
    /**
     * Print blank attendance sheet.
     */
    public function printBlankSheet(Request $request)
    {
        $validated = $request->validate([
            'classroom_id' => 'required|integer|exists:classrooms,id',
            'date' => 'required|date',
        ]);

        $classroom = Classroom::with('users')->find($validated['classroom_id']);
        $students = $classroom->users()->where('role', 'student_employee_participant')->get();

        $html = $this->generateBlankSheetHtml($classroom, $validated['date'], $students);

        return response()->streamDownload(
            function () use ($html) {
                echo $html;
            },
            "attendance-sheet-{$classroom->id}-{$validated['date']}.html"
        );
    }

    /**
     * Print daily attendance report.
     */
    public function printDailyAttendance(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'classroom_id' => 'nullable|integer|exists:classrooms,id',
        ]);

        $query = Attendance::whereDate('date', $validated['date']);

        if ($request->has('classroom_id')) {
            $query->where('classroom_id', $request->classroom_id);
        }

        $records = $query->with(['user', 'classroom'])->get();

        $html = $this->generateDailyReportHtml($validated['date'], $records);

        return response()->streamDownload(
            function () use ($html) {
                echo $html;
            },
            "daily-report-{$validated['date']}.html"
        );
    }

    /**
     * Print classroom attendance report.
     */
    public function printClassroomReport(Request $request)
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
            ->orderBy('date')
            ->get();

        $html = $this->generateClassroomReportHtml($classroom, $validated['date_from'], $validated['date_to'], $records);

        return response()->streamDownload(
            function () use ($html) {
                echo $html;
            },
            "classroom-report-{$classroom->id}-{$validated['date_from']}-{$validated['date_to']}.html"
        );
    }

    /**
     * Print individual attendance history.
     */
    public function printIndividualReport(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date',
        ]);

        $user = \App\Models\User::find($validated['user_id']);

        $this->ensureCanViewUser($user);

        $records = Attendance::where('user_id', $user->id)
            ->whereBetween('date', [$validated['date_from'], $validated['date_to']])
            ->with(['classroom', 'recorder'])
            ->orderBy('date', 'desc')
            ->get();

        $html = $this->generateIndividualReportHtml($user, $validated['date_from'], $validated['date_to'], $records);

        return response()->streamDownload(
            function () use ($html) {
                echo $html;
            },
            "individual-report-{$user->id}-{$validated['date_from']}-{$validated['date_to']}.html"
        );
    }

    // HTML Generation Methods

    private function generateBlankSheetHtml($classroom, $date, $students)
    {
        $html = '<html><head><meta charset="UTF-8"><title>Attendance Sheet</title>';
        $html .= '<style>body { font-family: Arial; margin: 20px; }';
        $html .= 'table { width: 100%; border-collapse: collapse; }';
        $html .= 'th, td { border: 1px solid black; padding: 10px; text-align: left; }';
        $html .= 'th { background-color: #f0f0f0; }';
        $html .= '.header { margin-bottom: 20px; }';
        $html .= 'h1 { margin: 0; }';
        $html .= '.signature-row { margin-top: 30px; }';
        $html .= '</style></head><body>';

        $html .= '<div class="header">';
        $html .= '<h1>Attendance Sheet</h1>';
        $html .= '<p><strong>Classroom:</strong> ' . htmlspecialchars($classroom->name) . '</p>';
        $html .= '<p><strong>Date:</strong> ' . $date . '</p>';
        $html .= '</div>';

        $html .= '<table>';
        $html .= '<thead><tr>';
        $html .= '<th>S.No</th>';
        $html .= '<th>Name</th>';
        $html .= '<th>Email</th>';
        $html .= '<th>Status</th>';
        $html .= '<th>Time In</th>';
        $html .= '<th>Remarks</th>';
        $html .= '</tr></thead><tbody>';

        $count = 1;
        foreach ($students as $student) {
            $html .= '<tr>';
            $html .= '<td>' . $count . '</td>';
            $html .= '<td>' . htmlspecialchars($student->name) . '</td>';
            $html .= '<td>' . htmlspecialchars($student->email) . '</td>';
            $html .= '<td>&nbsp;</td>';
            $html .= '<td>&nbsp;</td>';
            $html .= '<td>&nbsp;</td>';
            $html .= '</tr>';
            $count++;
        }

        $html .= '</tbody></table>';

        $html .= '<div class="signature-row">';
        $html .= '<p><strong>Teacher/Supervisor Signature:</strong> _____________________ &nbsp;&nbsp;&nbsp;&nbsp; Date: _____________</p>';
        $html .= '</div>';

        $html .= '</body></html>';

        return $html;
    }

    private function generateDailyReportHtml($date, $records)
    {
        $html = '<html><head><meta charset="UTF-8"><title>Daily Attendance Report</title>';
        $html .= '<style>body { font-family: Arial; margin: 20px; }';
        $html .= 'table { width: 100%; border-collapse: collapse; margin-top: 20px; }';
        $html .= 'th, td { border: 1px solid black; padding: 8px; text-align: left; }';
        $html .= 'th { background-color: #4CAF50; color: white; }';
        $html .= 'tr:nth-child(even) { background-color: #f2f2f2; }';
        $html .= '.summary { margin-bottom: 20px; }';
        $html .= 'h1 { margin: 0 0 10px 0; }';
        $html .= '</style></head><body>';

        $html .= '<h1>Daily Attendance Report</h1>';
        $html .= '<p><strong>Date:</strong> ' . $date . '</p>';

        $summary = [
            'total' => $records->count(),
            'present' => $records->where('status', 'Present')->count(),
            'absent' => $records->where('status', 'Absent')->count(),
            'late' => $records->where('status', 'Late')->count(),
            'excused' => $records->where('status', 'Excused')->count(),
        ];

        $html .= '<div class="summary">';
        $html .= '<h3>Summary</h3>';
        $html .= '<p>Total: ' . $summary['total'] . ' | Present: ' . $summary['present'] . ' | Absent: ' . $summary['absent'] . ' | Late: ' . $summary['late'] . ' | Excused: ' . $summary['excused'] . '</p>';
        $html .= '</div>';

        $html .= '<table>';
        $html .= '<thead><tr>';
        $html .= '<th>User Name</th>';
        $html .= '<th>Classroom</th>';
        $html .= '<th>Status</th>';
        $html .= '<th>Time In</th>';
        $html .= '<th>Remarks</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($records as $record) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($record->user->name) . '</td>';
            $html .= '<td>' . ($record->classroom ? htmlspecialchars($record->classroom->name) : 'N/A') . '</td>';
            $html .= '<td>' . htmlspecialchars($record->status) . '</td>';
            $html .= '<td>' . ($record->time_in ? htmlspecialchars($record->time_in) : 'N/A') . '</td>';
            $html .= '<td>' . ($record->remarks ? htmlspecialchars($record->remarks) : '') . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        $html .= '<p style="margin-top: 30px; font-size: 12px;">Generated on: ' . now()->format('Y-m-d H:i:s') . '</p>';
        $html .= '</body></html>';

        return $html;
    }

    private function generateClassroomReportHtml($classroom, $dateFrom, $dateTo, $records)
    {
        $html = '<html><head><meta charset="UTF-8"><title>Classroom Report</title>';
        $html .= '<style>body { font-family: Arial; margin: 20px; }';
        $html .= 'table { width: 100%; border-collapse: collapse; margin-top: 20px; }';
        $html .= 'th, td { border: 1px solid black; padding: 8px; text-align: left; }';
        $html .= 'th { background-color: #2196F3; color: white; }';
        $html .= 'tr:nth-child(even) { background-color: #f2f2f2; }';
        $html .= 'h1 { margin: 0 0 10px 0; }';
        $html .= '</style></head><body>';

        $html .= '<h1>Classroom Attendance Report</h1>';
        $html .= '<p><strong>Classroom:</strong> ' . htmlspecialchars($classroom->name) . '</p>';
        $html .= '<p><strong>Period:</strong> ' . $dateFrom . ' to ' . $dateTo . '</p>';

        $html .= '<table>';
        $html .= '<thead><tr>';
        $html .= '<th>Date</th>';
        $html .= '<th>User Name</th>';
        $html .= '<th>Status</th>';
        $html .= '<th>Time In</th>';
        $html .= '<th>Time Out</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($records as $record) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($record->date) . '</td>';
            $html .= '<td>' . htmlspecialchars($record->user->name) . '</td>';
            $html .= '<td>' . htmlspecialchars($record->status) . '</td>';
            $html .= '<td>' . ($record->time_in ? htmlspecialchars($record->time_in) : 'N/A') . '</td>';
            $html .= '<td>' . ($record->time_out ? htmlspecialchars($record->time_out) : 'N/A') . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        $html .= '<p style="margin-top: 30px; font-size: 12px;">Generated on: ' . now()->format('Y-m-d H:i:s') . '</p>';
        $html .= '</body></html>';

        return $html;
    }

    private function generateIndividualReportHtml($user, $dateFrom, $dateTo, $records)
    {
        $html = '<html><head><meta charset="UTF-8"><title>Individual Report</title>';
        $html .= '<style>body { font-family: Arial; margin: 20px; }';
        $html .= 'table { width: 100%; border-collapse: collapse; margin-top: 20px; }';
        $html .= 'th, td { border: 1px solid black; padding: 8px; text-align: left; }';
        $html .= 'th { background-color: #FF9800; color: white; }';
        $html .= 'tr:nth-child(even) { background-color: #f2f2f2; }';
        $html .= '.user-info { background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px; }';
        $html .= 'h1 { margin: 0 0 10px 0; }';
        $html .= '</style></head><body>';

        $html .= '<h1>Individual Attendance Report</h1>';
        $html .= '<div class="user-info">';
        $html .= '<p><strong>Name:</strong> ' . htmlspecialchars($user->name) . '</p>';
        $html .= '<p><strong>Email:</strong> ' . htmlspecialchars($user->email) . '</p>';
        $html .= '<p><strong>Role:</strong> ' . htmlspecialchars($user->role) . '</p>';
        $html .= '<p><strong>Period:</strong> ' . $dateFrom . ' to ' . $dateTo . '</p>';

        $present = $records->where('status', 'Present')->count();
        $total = $records->count();
        $percentage = $total > 0 ? round(($present / $total) * 100, 2) : 0;

        $html .= '<p><strong>Attendance Rate:</strong> ' . $percentage . '% (' . $present . '/' . $total . ' days)</p>';
        $html .= '</div>';

        $html .= '<table>';
        $html .= '<thead><tr>';
        $html .= '<th>Date</th>';
        $html .= '<th>Classroom</th>';
        $html .= '<th>Status</th>';
        $html .= '<th>Time In</th>';
        $html .= '<th>Remarks</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($records as $record) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($record->date) . '</td>';
            $html .= '<td>' . ($record->classroom ? htmlspecialchars($record->classroom->name) : 'N/A') . '</td>';
            $html .= '<td>' . htmlspecialchars($record->status) . '</td>';
            $html .= '<td>' . ($record->time_in ? htmlspecialchars($record->time_in) : 'N/A') . '</td>';
            $html .= '<td>' . ($record->remarks ? htmlspecialchars($record->remarks) : '') . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        $html .= '<p style="margin-top: 30px; font-size: 12px;">Generated on: ' . now()->format('Y-m-d H:i:s') . '</p>';
        $html .= '</body></html>';

        return $html;
    }
}
