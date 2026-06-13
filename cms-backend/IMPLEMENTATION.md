# Classroom Record System - Implementation Guide

## Status

The project now covers the JSON source files in `follow/` at a practical MVP-plus level. The core classroom attendance workflows are implemented with Laravel API controllers, middleware, Sanctum authentication, a static frontend, scheduled commands, import/export support, notifications, generated user QR codes, print views, and audit logs for important write actions.

## Implemented

### Authentication and Access

- Sanctum bearer-token login, register, and logout.
- Role middleware for:
  - `super_admin`
  - `staff_teacher_supervisor`
  - `student_employee_participant`
- Dashboard access returns the current role and allowed frontend pages.
- Staff users are limited to attendance/classrooms they record or teach where the controller needs that restriction.

### Users and Classrooms

- User CRUD with deactivate instead of hard delete.
- Classroom CRUD with deactivate instead of hard delete.
- Assign students/participants to classrooms.
- Upload profile images.
- Generate, download, and print user QR codes.

### Attendance

- Manual attendance records.
- Present, Absent, Late, and Excused statuses.
- Duplicate same-day attendance checks.
- Optional attendance sessions for class/event tracking.
- Personal attendance history.
- Soft deletes on attendance records.

### Reports, Export, and Print

- Daily, weekly, monthly, classroom, and individual reports.
- Real Excel export through `.xlsx`.
- Real PDF export through FPDF.
- Printable blank sheets, daily reports, classroom reports, individual reports, and QR sheets.
- Export records are logged.

### Import

- User and attendance imports.
- CSV/TXT support.
- Excel `.xlsx` and `.xls` support.
- Import logs store row counts and row-level errors.

### Notifications and Automation

- Notification records for email messages.
- API endpoints to list, create, mark read, and send pending notifications.
- Scheduled commands:
  - `attendance:missing-submissions`
  - `reports:daily-summary`
  - `notifications:send-pending`
- Email notifications use Laravel mail.

### Audit Logs

Audit records are written for sensitive changes including:

- user registration, create, update, deactivate, profile image update, QR generation
- classroom create, update, deactivate, assignment
- attendance create, update, delete
- attendance session create, update, delete
- import processing
- report export
- notification creation

## API Notes

### Public

- `POST /api/login`
- `POST /api/register`

### Authenticated

- `POST /api/logout`
- `GET /api/dashboard`
- `GET /api/dashboard/summary`
- `GET /api/notifications`
- `PATCH /api/notifications/{notification}/read`

### Staff/Admin

- `/api/attendance`
- `/api/attendance-sessions`
- `/api/reports/*`
- `/api/print/*`
- `/api/qr/users`
- `/api/qr/generate/{user}`
- `/api/qr/print-all`

### Admin

- `/api/users`
- `/api/classrooms`
- `/api/imports`
- `/api/notifications`

## Run

```bash
cd cms-backend
composer install
php artisan migrate --force
php artisan db:seed
php artisan serve
```

Open the frontend from `cms-frontend/index.html` or serve it with a static server.

## Demo Users

After seeding:

- `admin@classroom.local` / `password`
- `teacher@classroom.local` / `password`
- `student@classroom.local` / `password`

## Verification

Current test suite:

```bash
php artisan test
```

Coverage includes Sanctum login, dashboard access, Excel/PDF report exports, Excel import, attendance sessions, and notifications.

## Remaining Configuration

The code paths are present, but production sending still needs environment configuration:

- mail settings for email delivery
- scheduler/queue worker process in production
