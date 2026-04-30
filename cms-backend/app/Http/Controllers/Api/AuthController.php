<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    private const SESSION_HOURS = 8;

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'student_employee_participant',
            'status' => 'active',
        ]);

        return response()->json($user);
    }

    public function login(Request $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'The email address or password is incorrect.'], 401);
        }

        if ($user->status !== 'active') {
            return response()->json(['message' => 'Account is not active'], 403);
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
}
