<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['System Administrator', 'admin@classroom.local', 'super_admin'],
            ['Test Teacher', 'teacher@classroom.local', 'staff_teacher_supervisor'],
            ['Test Student', 'student@classroom.local', 'student_employee_participant'],
        ];

        foreach ($users as [$name, $email, $role]) {
            User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => Hash::make('password'),
                'role' => $role,
                'status' => 'active',
            ]);
        }
    }
}
