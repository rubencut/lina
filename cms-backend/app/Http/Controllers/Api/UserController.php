<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('classroom_id')) {
            $query->where('classroom_id', $request->classroom_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate($request->per_page ?? 15);
        $users->getCollection()->transform(function (User $user) {
            if ($user->qr_code) {
                $user->qr_image = QrCodeController::dataUrlFor($user);
            }

            return $user;
        });

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|in:super_admin,staff_teacher_supervisor,student_employee_participant',
            'classroom_id' => 'nullable|integer|exists:classrooms,id',
            'status' => 'required|in:active,inactive,suspended',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['qr_code'] = Str::uuid()->toString();

        $user = User::create($validated);
        $this->audit('user.created', $user, null, $user->toArray());
        $this->audit('user.qr_generated', $user, null, ['qr_code' => $user->qr_code]);

        $notificationSent = $this->sendVerificationNotification($user);
        $responseUser = $user->fresh();
        $responseUser->verification_required = $responseUser->hasPendingEmailVerification();
        $responseUser->notification_sent = $notificationSent;

        return response()->json($responseUser, 201);
    }

    public function show(User $user)
    {
        return response()->json($user->load(['classroom', 'teacherClassrooms']));
    }

    public function update(Request $request, User $user)
    {
        $old = $user->only(['name', 'email', 'phone', 'role', 'classroom_id', 'status']);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,'.$user->id,
            'phone' => 'nullable|string|max:20',
            'role' => 'sometimes|in:super_admin,staff_teacher_supervisor,student_employee_participant',
            'classroom_id' => 'nullable|integer|exists:classrooms,id',
            'status' => 'sometimes|in:active,inactive,suspended',
        ]);

        $user->update($validated);
        $this->audit('user.updated', $user, $old, $user->fresh()->toArray());

        return response()->json($user);
    }

    public function destroy(User $user)
    {
        $old = $user->only(['status']);
        $user->update(['status' => 'inactive']);
        $this->audit('user.deactivated', $user, $old, ['status' => 'inactive']);

        return response()->json(['message' => 'User deactivated successfully']);
    }

    public function uploadProfileImage(Request $request, User $user)
    {
        $validated = $request->validate([
            'profile_image' => 'required|image|max:2048',
        ]);

        if ($request->hasFile('profile_image')) {
            $path = $request->file('profile_image')->store('profile_images', 'public');
            $old = $user->only(['profile_image']);
            $user->update(['profile_image' => $path]);
            $this->audit('user.profile_image_updated', $user, $old, ['profile_image' => $path]);
        }

        return response()->json($user);
    }

    private function sendVerificationNotification(User $user): bool
    {
        $code = $user->startEmailVerification();

        if (! $code) {
            return false;
        }

        return app(NotificationController::class)->sendVerificationCode($user->fresh(), $code);
    }

}
