<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $query = Notification::with('user')->latest();

        if (!$request->user()->isSuperAdmin()) {
            $query->where('user_id', $request->user()->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->paginate($request->per_page ?? 15));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'type' => 'required|in:email',
            'message' => 'required|string',
        ]);

        $notification = $this->queueEmail(User::findOrFail($validated['user_id']), $validated['message']);

        return response()->json($notification->load('user'), 201);
    }

    public function queueEmail(User $user, string $message): Notification
    {
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'email',
            'message' => $message,
            'status' => 'pending',
        ]);

        $this->audit('notification.created', $notification, null, $notification->toArray());

        return $notification;
    }

    public function queueVerificationCode(User $user, string $code): Notification
    {
        return $this->queueEmail(
            $user,
            "Hello {$user->name},\n\nYour Classroom Record System verification code is {$code}. It expires in 30 minutes."
        );
    }

    public function sendVerificationCode(User $user, string $code): bool
    {
        return $this->send($this->queueVerificationCode($user, $code));
    }

    public function markRead(Notification $notification)
    {
        $this->ensureNotificationOwner($notification);
        $notification->update(['status' => 'read']);

        return response()->json($notification);
    }

    public function sendPending()
    {
        $sent = 0;
        $failed = 0;

        Notification::pending()->with('user')->chunkById(50, function ($notifications) use (&$sent, &$failed) {
            foreach ($notifications as $notification) {
                $this->send($notification) ? $sent++ : $failed++;
            }
        });

        return response()->json(compact('sent', 'failed'));
    }

    public function send(Notification $notification): bool
    {
        $user = $notification->user;

        if (!$user instanceof User) {
            $notification->update(['status' => 'failed']);
            return false;
        }

        if ($notification->type !== 'email') {
            $notification->update(['status' => 'failed']);
            return false;
        }

        try {
            Mail::raw($notification->message, fn ($mail) => $mail
                ->to($user->email)
                ->subject('Classroom Record System'));

            $notification->update(['status' => 'sent', 'sent_at' => now()]);
            return true;
        } catch (\Throwable) {
            $notification->update(['status' => 'failed']);
            return false;
        }
    }

    private function ensureNotificationOwner(Notification $notification): void
    {
        $user = request()->user();

        if (!$user->isSuperAdmin() && (int) $notification->user_id !== (int) $user->id) {
            abort(403, 'You are not allowed to access this notification.');
        }
    }
}
