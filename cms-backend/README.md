# Classroom Record System

Laravel API for a classroom attendance system with a static frontend in `../cms-frontend`.

## Features

- Sanctum login/register/logout
- role-based access for admin, teacher/supervisor, and student/participant users
- user, classroom, attendance, and attendance session management
- generated user QR codes
- Excel import and Excel/PDF report export
- printable reports and sheets
- notification records with email send hooks
- audit logs for sensitive writes

## Setup

```bash
composer install
php artisan migrate --force
php artisan db:seed
php artisan serve
```

The frontend expects the API at:

```text
http://127.0.0.1:9000/api
```

## Demo Users

After seeding:

- `admin@classroom.local` / `password`
- `teacher@classroom.local` / `password`
- `student@classroom.local` / `password`

## Tests

```bash
php artisan test
```

## Notes

Configure mail, the scheduler, and queue workers in the deployment environment before using notifications in production.
