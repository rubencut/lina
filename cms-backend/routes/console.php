<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Notification;
use App\Models\User;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('attendance:missing-submissions {date?}', function (?string $date = null) {
    $date ??= now()->toDateString();
    $created = 0;

    Classroom::where('status', 'active')->with('teacher')->chunkById(50, function ($classrooms) use ($date, &$created) {
        foreach ($classrooms as $classroom) {
            if (!$classroom->teacher) {
                continue;
            }

            $hasRecords = Attendance::where('classroom_id', $classroom->id)->whereDate('date', $date)->exists();

            if (!$hasRecords) {
                Notification::firstOrCreate([
                    'user_id' => $classroom->teacher->id,
                    'type' => 'email',
                    'message' => "Attendance for {$classroom->name} has not been submitted for {$date}.",
                ], ['status' => 'pending']);

                $created++;
            }
        }
    });

    $this->info("Created {$created} missing-submission reminders.");
})->purpose('Create reminders for classrooms without attendance records.');

Artisan::command('notifications:send-pending', function () {
    $sent = 0;
    $failed = 0;

    Notification::pending()->with('user')->chunkById(50, function ($notifications) use (&$sent, &$failed) {
        foreach ($notifications as $notification) {
            try {
                if ($notification->type !== 'email' || !$notification->user?->email) {
                    throw new RuntimeException('Notification has no valid recipient.');
                }

                Mail::raw($notification->message, fn ($mail) => $mail
                    ->to($notification->user->email)
                    ->subject('Classroom Record System'));

                $notification->update(['status' => 'sent', 'sent_at' => now()]);
                $sent++;
            } catch (Throwable) {
                $notification->update(['status' => 'failed']);
                $failed++;
            }
        }
    });

    $this->info("Sent {$sent}; failed {$failed}.");
})->purpose('Send queued notification records.');

Artisan::command('reports:daily-summary {date?}', function (?string $date = null) {
    $date ??= now()->toDateString();
    $total = Attendance::whereDate('date', $date)->count();
    $present = Attendance::whereDate('date', $date)->where('status', 'Present')->count();
    $absent = Attendance::whereDate('date', $date)->where('status', 'Absent')->count();

    User::where('role', 'super_admin')->where('status', 'active')->each(function ($user) use ($date, $total, $present, $absent) {
        Notification::create([
            'user_id' => $user->id,
            'type' => 'email',
            'message' => "Daily attendance summary for {$date}: {$total} records, {$present} present, {$absent} absent.",
            'status' => 'pending',
        ]);
    });

    $this->info('Daily report notifications queued.');
})->purpose('Queue daily attendance summary notifications.');

Schedule::command('attendance:missing-submissions')->dailyAt('17:00');
Schedule::command('reports:daily-summary')->dailyAt('18:00');
Schedule::command('notifications:send-pending')->everyFifteenMinutes();
