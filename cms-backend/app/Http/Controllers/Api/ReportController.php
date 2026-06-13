<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Classroom;
use App\Models\Export;
use App\Models\User;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $query = AttendanceSession::whereNotNull('submitted_at');
        $user = $request->user();

        if ($user->isStudentEmployeeParticipant()) {
            $query->where('classroom_id', $user->classroom_id);
        } elseif ($user->isStaffTeacherSupervisor()) {
            $query->whereHas('classroom', fn ($classroom) => $classroom->where('teacher_id', $user->id));
        }

        if ($request->filled('classroom_id')) {
            $query->where('classroom_id', $request->classroom_id);
        }

        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }

        return response()->json(
            $query->with(['classroom.teacher'])
                ->withCount('attendance')
                ->latest('date')
                ->paginate($request->per_page ?? 25)
        );
    }

    public function show(AttendanceSession $report)
    {
        $user = request()->user();

        if ($user->isStaffTeacherSupervisor() && (int) $report->classroom?->teacher_id !== (int) $user->id) {
            abort(403, 'You are not allowed to view this report.');
        }

        if ($user->isStudentEmployeeParticipant() && (int) $report->classroom_id !== (int) $user->classroom_id) {
            abort(403, 'You are not allowed to view this report.');
        }

        $attendance = $report->attendance()
            ->with('user')
            ->when($user->isStudentEmployeeParticipant(), fn ($query) => $query->where('user_id', $user->id))
            ->orderBy('status')
            ->orderBy('time_in')
            ->get();

        if ($user->isStudentEmployeeParticipant() && $attendance->isEmpty()) {
            abort(403, 'You are not allowed to view this report.');
        }

        return response()->json([
            'report' => $report->load('classroom.teacher'),
            'attendance' => $attendance,
        ]);
    }

    public function weeklyAttendance(Request $request)
    {
        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date',
            'classroom_id' => 'nullable|integer|exists:classrooms,id',
        ]);

        $query = Attendance::whereNotNull('attendance_session_id')
            ->whereBetween('date', [$validated['date_from'], $validated['date_to']]);

        if ($request->has('classroom_id')) {
            $query->where('classroom_id', $request->classroom_id);
        }

        $records = $query->with(['user', 'classroom'])->get();

        $grouped = $records->groupBy('user_id')->map(function ($group) {
            return [
                'user_id' => $group[0]->user->id,
                'user_name' => $group[0]->user->name,
                ...$this->summary($group),
            ];
        });

        return response()->json([
            'period' => ['from' => $validated['date_from'], 'to' => $validated['date_to']],
            'total_records' => $records->count(),
            'summary' => $grouped->values(),
        ]);
    }

    public function monthlyAttendance(Request $request)
    {
        $validated = $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer',
            'classroom_id' => 'nullable|integer|exists:classrooms,id',
        ]);

        $startDate = date('Y-m-01', strtotime("{$validated['year']}-{$validated['month']}-01"));
        $endDate = date('Y-m-t', strtotime($startDate));

        $query = Attendance::whereNotNull('attendance_session_id')
            ->whereBetween('date', [$startDate, $endDate]);

        if ($request->has('classroom_id')) {
            $query->where('classroom_id', $request->classroom_id);
        }

        $records = $query->with(['user', 'classroom'])->get();

        $grouped = $records->groupBy('user_id')->map(function ($group) {
            return [
                'user_id' => $group[0]->user->id,
                'user_name' => $group[0]->user->name,
                ...$this->summary($group),
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

    public function classroomReport(Request $request)
    {
        $validated = $request->validate([
            'classroom_id' => 'required|integer|exists:classrooms,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date',
        ]);

        $classroom = Classroom::find($validated['classroom_id']);

        $records = Attendance::whereNotNull('attendance_session_id')
            ->where('classroom_id', $classroom->id)
            ->whereBetween('date', [$validated['date_from'], $validated['date_to']])
            ->with(['user', 'classroom'])
            ->get();

        $grouped = $records->groupBy('user_id')->map(function ($group) {
            return [
                'user_name' => $group[0]->user->name,
                'total_days' => $group->count(),
                ...$this->summary($group, false),
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

    public function individualReport(Request $request, User $user)
    {
        $this->ensureCanViewUser($user);

        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date',
        ]);

        $records = Attendance::whereNotNull('attendance_session_id')
            ->where('user_id', $user->id)
            ->whereBetween('date', [$validated['date_from'], $validated['date_to']])
            ->with(['classroom', 'recorder'])
            ->orderBy('date', 'desc')
            ->get();

        return response()->json([
            'user' => $user->name,
            'period' => ['from' => $validated['date_from'], 'to' => $validated['date_to']],
            'total_days' => $records->count(),
            ...$this->summary($records, false),
            'attendance_percentage' => $records->count() > 0
                ? round(($records->where('status', 'Present')->count() / $records->count()) * 100, 2)
                : 0,
            'records' => $records,
        ]);
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'report_type' => 'required|in:reports,daily,weekly,monthly,classroom,individual',
            'format' => 'required|in:Excel,PDF',
            'date' => 'nullable|date',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'classroom_id' => 'nullable|integer|exists:classrooms,id',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        $records = $validated['report_type'] === 'reports'
            ? $this->exportReportSessions($validated)
            : $this->exportRecords($validated);
        $export = Export::create([
            'exported_by' => auth()->id(),
            'type' => $validated['report_type'],
            'format' => $validated['format'],
            'file_path' => null,
            'filters' => $validated,
        ]);

        $this->audit('report.exported', $export, null, $export->toArray());

        if ($validated['report_type'] === 'reports') {
            return $validated['format'] === 'PDF'
                ? $this->downloadReportsPdf($records, $validated)
                : $this->downloadReportsExcel($records, $validated);
        }

        return $validated['format'] === 'PDF'
            ? $this->downloadPdf($records, $validated)
            : $this->downloadExcel($records, $validated);
    }

    private function exportReportSessions(array $filters)
    {
        $query = AttendanceSession::whereNotNull('submitted_at')
            ->with(['classroom.teacher'])
            ->withCount('attendance')
            ->orderBy('date');
        $user = auth()->user();

        if ($user->isStudentEmployeeParticipant()) {
            $query->where('classroom_id', $user->classroom_id);
        } elseif ($user->isStaffTeacherSupervisor()) {
            $query->whereHas('classroom', fn ($classroom) => $classroom->where('teacher_id', $user->id));
        }

        if (! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            $query->whereBetween('date', [$filters['date_from'], $filters['date_to']]);
        }

        if (! empty($filters['classroom_id'])) {
            $query->where('classroom_id', $filters['classroom_id']);
        }

        return $query->get();
    }

    private function exportRecords(array $filters)
    {
        $query = Attendance::whereNotNull('attendance_session_id')
            ->with(['user', 'classroom'])
            ->orderBy('date');
        $user = auth()->user();

        if ($user->isStudentEmployeeParticipant()) {
            $query->where('user_id', $user->id);
        } elseif ($user->isStaffTeacherSupervisor()) {
            $query->where(function ($q) use ($user) {
                $q->where('recorded_by', $user->id)
                    ->orWhereHas('classroom', fn ($classroom) => $classroom->where('teacher_id', $user->id));
            });
        }

        if (($filters['report_type'] ?? null) === 'daily' && ! empty($filters['date'])) {
            $query->whereDate('date', $filters['date']);
        }

        if (! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            $query->whereBetween('date', [$filters['date_from'], $filters['date_to']]);
        }

        if (! empty($filters['classroom_id'])) {
            $query->where('classroom_id', $filters['classroom_id']);
        }

        if (! empty($filters['user_id']) && ! $user->isStudentEmployeeParticipant()) {
            $query->where('user_id', $filters['user_id']);
        }

        return $query->get();
    }

    private function downloadExcel($records, array $filters)
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['Date', 'User', 'Classroom', 'Status', 'Time In'], null, 'A1');

        $row = 2;
        foreach ($records as $record) {
            $sheet->fromArray([
                optional($record->date)->toDateString(),
                $record->user?->name,
                $record->classroom?->name,
                $record->status,
                $record->time_in,
            ], null, "A{$row}");
            $row++;
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(fn () => $writer->save('php://output'), $this->exportName($filters, 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function downloadReportsExcel($reports, array $filters)
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['Date', 'Classroom', 'Teacher', 'Description', 'Submitted', 'Students'], null, 'A1');

        $row = 2;
        foreach ($reports as $report) {
            $sheet->fromArray([
                optional($report->date)->toDateString(),
                $report->classroom?->name,
                $report->classroom?->teacher?->name,
                $report->classroom?->description,
                optional($report->submitted_at)->format('Y-m-d H:i'),
                $report->attendance_count,
            ], null, "A{$row}");
            $row++;
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(fn () => $writer->save('php://output'), $this->exportName($filters, 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function downloadPdf($records, array $filters)
    {
        $pdf = new \FPDF('L', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 8, $this->pdfText('Attendance Report'), 0, 1);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 6, $this->pdfText('Generated: '.now()->format('Y-m-d H:i')), 0, 1);
        $pdf->Ln(3);

        foreach ($this->pdfColumns() as $title => $width) {
            $pdf->Cell($width, 7, $this->pdfText($title), 1);
        }
        $pdf->Ln();

        foreach ($records as $record) {
            $values = [
                optional($record->date)->toDateString(),
                $record->user?->name,
                $record->classroom?->name ?? 'N/A',
                $record->status,
                (string) $record->time_in,
            ];

            foreach (array_values($this->pdfColumns()) as $index => $width) {
                $pdf->Cell($width, 7, $this->pdfText(substr((string) $values[$index], 0, 45)), 1);
            }
            $pdf->Ln();
        }

        return response($pdf->Output('S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->exportName($filters, 'pdf').'"',
        ]);
    }

    private function downloadReportsPdf($reports, array $filters)
    {
        $pdf = new \FPDF('L', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 8, $this->pdfText('Classroom Attendance Reports'), 0, 1);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 6, $this->pdfText('Generated: '.now()->format('Y-m-d H:i')), 0, 1);
        $pdf->Ln(3);

        foreach ($this->reportPdfColumns() as $title => $width) {
            $pdf->Cell($width, 7, $this->pdfText($title), 1);
        }
        $pdf->Ln();

        foreach ($reports as $report) {
            $values = [
                optional($report->date)->toDateString(),
                $report->classroom?->name,
                $report->classroom?->teacher?->name,
                $report->classroom?->description,
                optional($report->submitted_at)->format('Y-m-d H:i'),
                $report->attendance_count,
            ];

            foreach (array_values($this->reportPdfColumns()) as $index => $width) {
                $pdf->Cell($width, 7, $this->pdfText(substr((string) $values[$index], 0, 45)), 1);
            }
            $pdf->Ln();
        }

        return response($pdf->Output('S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->exportName($filters, 'pdf').'"',
        ]);
    }

    private function exportName(array $filters, string $extension): string
    {
        return 'attendance-'.($filters['report_type'] ?? 'report').'-'.now()->format('Ymd-His').'.'.$extension;
    }

    private function pdfText(?string $text): string
    {
        return iconv('UTF-8', 'windows-1252//TRANSLIT', $text ?? '') ?: '';
    }

    private function summary($records, bool $includeTotal = true): array
    {
        return array_filter([
            'total' => $includeTotal ? $records->count() : null,
            'present' => $records->where('status', 'Present')->count(),
            'absent' => $records->where('status', 'Absent')->count(),
            'late' => $records->where('status', 'Late')->count(),
            'excused' => $records->where('status', 'Excused')->count(),
        ], fn ($value) => $value !== null);
    }

    private function pdfColumns(): array
    {
        return ['Date' => 30, 'User' => 70, 'Classroom' => 65, 'Status' => 35, 'Time In' => 35];
    }

    private function reportPdfColumns(): array
    {
        return ['Date' => 25, 'Classroom' => 55, 'Teacher' => 55, 'Description' => 75, 'Submitted' => 35, 'Students' => 25];
    }
}
