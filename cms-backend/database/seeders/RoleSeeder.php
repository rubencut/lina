<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the roles table.
     */
    public function run(): void
    {
        Role::updateOrCreate(['name' => 'super_admin'], [
            'name' => 'super_admin',
            'description' => 'Full system control. Manages users, classrooms, settings, reports, and monitoring.',
        ]);

        Role::updateOrCreate(['name' => 'staff_teacher_supervisor'], [
            'name' => 'staff_teacher_supervisor',
            'description' => 'Manages attendance for assigned users, classrooms, or groups.',
        ]);

        Role::updateOrCreate(['name' => 'student_employee_participant'], [
            'name' => 'student_employee_participant',
            'description' => 'Views personal attendance information only.',
        ]);
    }
}
