<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private const SESSION_HOURS = 8;

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => trim($validated['name']),
            'email' => Str::lower(trim($validated['email'])),
            'password' => Hash::make($validated['password']),
            'role' => 'student_employee_participant',
            'status' => 'active',
        ]);
        $this->audit('user.registered', $user, null, $user->toArray());

        $notificationSent = $this->sendVerificationNotification($user);

        return response()->json([
            'message' => 'Account created. Check your email for the verification code.',
            'user' => $user->fresh(),
            'verification_required' => $user->fresh()->hasPendingEmailVerification(),
            'notification_sent' => $notificationSent,
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $email = Str::lower(trim($validated['email']));
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'The email address or password is incorrect.'], 401);
        }

        if ($user->status !== 'active') {
            return response()->json(['message' => 'Account is not active.'], 403);
        }

        if ($user->hasPendingEmailVerification()) {
            if ($user->email_verification_expires_at?->isPast()) {
                $this->sendVerificationNotification($user);
            }

            return response()->json([
                'message' => 'Verification code required. Check your email and enter the code to continue.',
                'verification_required' => true,
                'email' => $user->email,
            ], 409);
        }

        $expiresAt = now()->addHours(self::SESSION_HOURS);
        $token = $user->createToken('auth_token', ['*'], $expiresAt)->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'expires_at' => $expiresAt->toISOString(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Signed out successfully.',
        ]);
    }

    public function verifyCode(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        $email = Str::lower(trim($validated['email']));
        $user = User::where('email', $email)->first();

        if (! $user || ! $user->hasPendingEmailVerification()) {
            return response()->json(['message' => 'No pending verification was found for this email.'], 422);
        }

        if ($user->email_verification_expires_at?->isPast()) {
            $this->sendVerificationNotification($user);

            return response()->json([
                'message' => 'Verification code expired. A new code was sent to your email.',
                'verification_required' => true,
                'email' => $user->email,
            ], 422);
        }

        if (! $user->verificationCodeMatches($validated['code'])) {
            return response()->json(['message' => 'The verification code is incorrect.'], 422);
        }

        $user->completeEmailVerification();
        $this->audit('user.email_verified', $user, null, ['email_verified_at' => $user->email_verified_at]);

        return response()->json([
            'message' => 'Email verified successfully.',
            'user' => $user->fresh(),
        ]);
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
