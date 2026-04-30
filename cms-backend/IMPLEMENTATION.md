# Classroom Record System - Implementation Guide

## Project Overview
The **Classroom Record System** is a comprehensive Laravel-based attendance management platform designed for schools, training centers, offices, and organizations. It provides centralized attendance tracking, reporting, and management with role-based access control.

## ✅ Completed Implementation (Phase 1 - MVP)

### Database & Models ✓
- **Migrations**: All 11 migration files created
  - `roles`, `classrooms`, `attendance`, `notifications`, `imports`, `exports`, `audit_logs`, `attendance_sessions`
  - Enhanced `users` table with additional fields (phone, profile_image, qr_code, role, status, classroom_id)

- **Models**: All models with relationships created
  - `User`, `Classroom`, `Attendance`, `Notification`, `Import`, `Export`, `AuditLog`, `AttendanceSession`, `Role`

### Authentication & Authorization ✓
- **AuthServiceProvider**: Policies and gates configured
- **Policies**: 
  - `UserPolicy`: Control user viewing/editing
  - `ClassroomPolicy`: Control classroom access
  - `AttendancePolicy`: Control attendance record access

- **Gates**: 
  - Role-based gates: `super_admin`, `staff_teacher_supervisor`, `student_employee_participant`
  - Permission gates: `manage-users`, `manage-classrooms`, `mark-attendance`, `view-reports`, `export-data`, `import-data`

### API Controllers ✓
- **AuthController**: Login, logout, user info, token refresh
- **UserController**: CRUD operations, profile image upload, QR code generation
- **ClassroomController**: CRUD operations, student assignment
- **AttendanceController**: Mark attendance, QR scanning, history retrieval, summary
- **ReportController**: Daily, weekly, monthly reports, exports (CSV)

### API Routes ✓
- All protected endpoints use `auth:sanctum`, with `role:*` groups for role access.
- RESTful API endpoints for all resources
- Report generation endpoints

### Database Seeder ✓
- `RoleSeeder`: Creates 3 system roles
- `DatabaseSeeder`: Creates demo users (admin, teacher, student)

### Frontend ✓
- Basic dashboard interface with login/logout
- Responsive design with clean UI
- Placeholder for full implementation

## 🚀 How to Run

### Backend Setup
```bash
cd cms-backend

# Install dependencies
composer install

# Create .env file (copy from .env.example)
cp .env.example .env

# Generate app key
php artisan key:generate

# Run migrations
php artisan migrate

# Seed the database
php artisan db:seed

# Start the development server
php artisan serve
```

### Frontend Setup
```bash
cd cms-frontend

# Open index.html in a web browser or serve with:
python -m http.server 8001
# or
npx http-server
```

## 📋 Available Test Users

After seeding, these users are available:

1. **Super Admin**
   - Email: `admin@classroom.local`
   - Password: (check UserFactory for generated password)
   - Role: Super Admin

2. **Teacher**
   - Email: `teacher@classroom.local`
   - Role: Staff/Teacher/Supervisor

3. **Student**
   - Email: `student@classroom.local`
   - Role: Student/Employee/Participant

## 🔧 API Endpoints

### Authentication
- `POST /api/login` - Login and get token
- `GET /api/dashboard` - Get current role and allowed pages
- `POST /api/logout` - Logout

### Users
- `GET /api/users` - List all users
- `POST /api/users` - Create user
- `GET /api/users/{id}` - Get user details
- `PUT /api/users/{id}` - Update user
- `DELETE /api/users/{id}` - Deactivate user
- `POST /api/users/{id}/upload-profile-image` - Upload profile image
- `POST /api/users/{id}/generate-qr-code` - Generate QR code

### Classrooms
- `GET /api/classrooms` - List classrooms
- `POST /api/classrooms` - Create classroom
- `GET /api/classrooms/{id}` - Get classroom details
- `PUT /api/classrooms/{id}` - Update classroom
- `DELETE /api/classrooms/{id}` - Deactivate classroom
- `GET /api/classrooms/{id}/students` - Get students in classroom
- `POST /api/classrooms/{id}/assign-users` - Assign users to classroom

### Attendance
- `GET /api/attendance` - List attendance records
- `POST /api/attendance` - Mark attendance
- `GET /api/attendance/{id}` - Get attendance record
- `PUT /api/attendance/{id}` - Update attendance
- `DELETE /api/attendance/{id}` - Delete attendance record
- `POST /api/attendance/mark-by-qr` - Mark attendance using QR code
- `GET /api/attendance/personal-history` - Get personal attendance history
- `GET /api/attendance/summary` - Get attendance summary

### Reports
- `GET /api/reports/daily` - Daily attendance report
- `GET /api/reports/weekly` - Weekly attendance report
- `GET /api/reports/monthly` - Monthly attendance report
- `GET /api/reports/classroom` - Classroom attendance report
- `GET /api/reports/individual/{user_id}` - Individual attendance report
- `GET /api/reports/export-csv` - Export attendance as CSV

## 📊 Database Schema

### Users Table
- id, name, email, phone, profile_image, qr_code, role, classroom_id, status, timestamps

### Classrooms Table
- id, name, teacher_id, description, status, timestamps

### Attendance Table
- id, user_id, classroom_id, date, time_in, time_out, status, remarks, recorded_by, timestamps, soft_delete

### Additional Tables
- notifications, imports, exports, audit_logs, attendance_sessions, roles

## 🔐 Role-Based Access Control

### Super Admin
- Full system access
- Manage all users, classrooms, settings
- View all reports and logs

### Staff/Teacher/Supervisor
- Mark and manage attendance for assigned classrooms
- View assigned classroom reports
- Cannot manage system users

### Student/Employee/Participant
- View only personal attendance history
- Download own reports
- Limited read-only access

## 📝 Notes

- All attendance records support soft deletes for audit trail preservation
- Unique constraint on (user_id + classroom_id + date) prevents duplicate attendance
- Authorization is enforced at both policy and gate levels
- Audit logs track all sensitive changes
- QR code system prevents duplicate same-day attendance

## 🔄 Next Steps (Phase 2 & 3)

### Phase 2
- [ ] QR code scanning interface
- [ ] SMS notifications
- [ ] Cron job reminders
- [ ] Bulk import functionality
- [ ] Print layouts
- [ ] Complete audit logging

### Phase 3
- [ ] Advanced analytics and charts
- [ ] Event-based tracking
- [ ] Leave request workflow
- [ ] Mobile PWA
- [ ] Dashboard analytics

## 📚 Technology Stack

- **Backend**: Laravel 11 (PHP 8.1+)
- **Database**: MySQL / SQLite
- **Authentication**: first-party bearer token stored as a SHA-256 hash
- **Authorization**: Policies & Gates
- **Frontend**: Vanilla JavaScript + HTML5 + CSS3
- **API**: RESTful JSON API

## 🐛 Troubleshooting

### Database connection issues
- Check `.env` file database configuration
- Ensure database exists and is accessible
- Run: `php artisan migrate:fresh --seed` to reset

### CORS issues
- Update `.env` with correct frontend URL
- Configure CORS in Laravel if needed

### Authorization denied
- Check user role and permissions
- Verify token is valid by calling `GET /api/dashboard` with the bearer token

## 📞 Support

For issues or feature requests, refer to the specification JSON files in the `/follow` directory.
