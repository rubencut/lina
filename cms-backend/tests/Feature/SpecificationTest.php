<?php

use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Export;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

function adminToken($case): string
{
    User::create([
        'name' => 'Admin',
        'email' => 'admin@classroom.local',
        'password' => Hash::make('password'),
        'role' => 'super_admin',
        'status' => 'active',
    ]);

    return $case->postJson('/api/login', [
        'email' => 'admin@classroom.local',
        'password' => 'password',
    ])->json('token');
}

test('attendance reports export real excel and pdf files', function () {
    $token = adminToken($this);
    $student = User::create([
        'name' => 'Student',
        'email' => 'student@classroom.local',
        'password' => Hash::make('password'),
        'role' => 'student_employee_participant',
        'status' => 'active',
    ]);

    Attendance::create([
        'user_id' => $student->id,
        'date' => '2026-05-01',
        'status' => 'Present',
        'recorded_by' => 1,
    ]);

    $this->withToken($token)
        ->get('/api/reports/export?report_type=daily&format=Excel&date=2026-05-01')
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $this->withToken($token)
        ->get('/api/reports/export?report_type=daily&format=PDF&date=2026-05-01')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect(Export::count())->toBe(2);
});

test('excel imports can create users', function () {
    $token = adminToken($this);
    $path = tempnam(sys_get_temp_dir(), 'users') . '.xlsx';

    $spreadsheet = new Spreadsheet();
    $spreadsheet->getActiveSheet()->fromArray([
        ['Name', 'Email', 'Password', 'Role', 'Phone', 'Status'],
        ['Imported User', 'imported@classroom.local', 'password', 'student_employee_participant', '', 'active'],
    ]);
    (new Xlsx($spreadsheet))->save($path);

    $upload = new UploadedFile($path, 'users.xlsx', null, null, true);

    $this->withToken($token)
        ->post('/api/imports', ['type' => 'users', 'file' => $upload])
        ->assertCreated();

    expect(User::where('email', 'imported@classroom.local')->exists())->toBeTrue();
});

test('attendance sessions and notifications are available', function () {
    $token = adminToken($this);
    $classroom = Classroom::create(['name' => 'Room A', 'status' => 'active']);

    $this->withToken($token)
        ->postJson('/api/attendance-sessions', [
            'classroom_id' => $classroom->id,
            'title' => 'Morning class',
            'date' => '2026-05-01',
        ])
        ->assertCreated();

    $this->withToken($token)
        ->postJson('/api/notifications', [
            'user_id' => 1,
            'type' => 'email',
            'message' => 'Test notification',
        ])
        ->assertCreated();

    $this->withToken($token)
        ->postJson('/api/notifications', [
            'user_id' => 1,
            'type' => 'sms',
            'message' => 'Test notification',
        ])
        ->assertUnprocessable();
});

test('created teachers and students must verify their email code before login', function () {
    $token = adminToken($this);

    foreach ([
        'staff_teacher_supervisor' => 'Teacher',
        'student_employee_participant' => 'Student',
    ] as $role => $label) {
        $email = strtolower($label).'-verify@classroom.local';

        $this->withToken($token)
            ->postJson('/api/users', [
                'name' => "{$label} Verify",
                'email' => $email,
                'password' => 'password123',
                'role' => $role,
                'status' => 'active',
            ])
            ->assertCreated()
            ->assertJsonPath('verification_required', true);

        $user = User::where('email', $email)->firstOrFail();

        expect($user->hasPendingEmailVerification())->toBeTrue()
            ->and(Notification::where('user_id', $user->id)->where('type', 'email')->exists())->toBeTrue();

        $this->postJson('/api/login', [
            'email' => $email,
            'password' => 'password123',
        ])
            ->assertStatus(409)
            ->assertJsonPath('verification_required', true);

        $user->forceFill([
            'email_verification_code' => Hash::make('123456'),
            'email_verification_expires_at' => now()->addMinutes(30),
        ])->save();

        $this->postJson('/api/verify-code', [
            'email' => $email,
            'code' => '123456',
        ])->assertOk();

        $this->postJson('/api/login', [
            'email' => $email,
            'password' => 'password123',
        ])->assertOk();
    }
});

test('teachers can mark assigned students present by qr code', function () {
    $teacher = User::create([
        'name' => 'Teacher',
        'email' => 'teacher@classroom.local',
        'password' => Hash::make('password'),
        'role' => 'staff_teacher_supervisor',
        'status' => 'active',
    ]);

    $classroom = Classroom::create([
        'name' => 'Room QR',
        'teacher_id' => $teacher->id,
        'status' => 'active',
    ]);

    $student = User::create([
        'name' => 'Student QR',
        'email' => 'student-qr@classroom.local',
        'password' => Hash::make('password'),
        'role' => 'student_employee_participant',
        'status' => 'active',
        'classroom_id' => $classroom->id,
        'qr_code' => 'student-qr-token',
    ]);

    $teacherToken = $this->postJson('/api/login', [
        'email' => 'teacher@classroom.local',
        'password' => 'password',
    ])->json('token');

    $this->withToken($teacherToken)
        ->postJson('/api/attendance/mark-by-qr', [
            'qr_code' => 'student-qr-token',
            'date' => '2026-06-06',
            'time_in' => '08:15',
        ])
        ->assertCreated()
        ->assertJsonPath('already_recorded', false)
        ->assertJsonPath('attendance.user_id', $student->id)
        ->assertJsonPath('attendance.status', 'Present');

    expect(Attendance::where('user_id', $student->id)->count())->toBe(1);

    $this->withToken($teacherToken)
        ->postJson('/api/attendance/mark-by-qr', [
            'qr_code' => 'student-qr-token',
            'date' => '2026-06-06',
        ])
        ->assertOk()
        ->assertJsonPath('already_recorded', true);

    expect(Attendance::where('user_id', $student->id)->count())->toBe(1);
});

test('non teachers cannot mark attendance by qr code', function () {
    $token = adminToken($this);

    $this->withToken($token)
        ->postJson('/api/attendance/mark-by-qr', [
            'qr_code' => 'student-qr-token',
            'date' => '2026-06-06',
        ])
        ->assertForbidden();
});
