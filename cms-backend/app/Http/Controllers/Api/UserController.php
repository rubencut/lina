<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
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

        return response()->json($users);
    }

    /**
     * Store a newly created user.
     */
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

        $user = User::create($validated);

        return response()->json($user, 201);
    }

    /**
     * Display a specific user.
     */
    public function show(User $user)
    {
        return response()->json($user->load(['classroom', 'teacherClassrooms']));
    }

    /**
     * Update a user.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'role' => 'sometimes|in:super_admin,staff_teacher_supervisor,student_employee_participant',
            'classroom_id' => 'nullable|integer|exists:classrooms,id',
            'status' => 'sometimes|in:active,inactive,suspended',
        ]);

        $user->update($validated);

        return response()->json($user);
    }

    /**
     * Delete a user (deactivate instead of delete).
     */
    public function destroy(User $user)
    {
        $user->update(['status' => 'inactive']);

        return response()->json(['message' => 'User deactivated successfully']);
    }

    /**
     * Update user profile picture.
     */
    public function uploadProfileImage(Request $request, User $user)
    {
        $validated = $request->validate([
            'profile_image' => 'required|image|max:2048',
        ]);

        if ($request->hasFile('profile_image')) {
            $path = $request->file('profile_image')->store('profile_images', 'public');
            $user->update(['profile_image' => $path]);
        }

        return response()->json($user);
    }

    /**
     * Generate QR code for user.
     */
    public function generateQrCode(User $user)
    {
        if (!$user->qr_code) {
            $user->update(['qr_code' => Str::uuid()->toString()]);
        }

        return response()->json([
            'qr_code' => $user->qr_code,
            'user_id' => $user->id,
        ]);
    }
}
