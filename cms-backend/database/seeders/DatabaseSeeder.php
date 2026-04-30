<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
        ]);

        User::updateOrCreate(['email' => 'admin@classroom.local'], [
            'name' => 'System Administrator',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        User::updateOrCreate(['email' => 'teacher@classroom.local'], [
            'name' => 'Test Teacher',
            'password' => Hash::make('password'),
            'role' => 'staff_teacher_supervisor',
            'status' => 'active',
        ]);

        User::updateOrCreate(['email' => 'student@classroom.local'], [
            'name' => 'Test Student',
            'password' => Hash::make('password'),
            'role' => 'student_employee_participant',
            'status' => 'active',
        ]);
    }
}
