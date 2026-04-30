<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('login returns a bearer token and the token can read the user', function () {
    $user = User::create([
        'name' => 'Admin',
        'email' => 'admin@classroom.local',
        'password' => Hash::make('password'),
        'role' => 'super_admin',
        'status' => 'active',
    ]);

    $login = $this->postJson('/api/login', [
        'email' => 'admin@classroom.local',
        'password' => 'password',
    ]);

    $login->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonStructure(['token']);

    $this->withToken($login->json('token'))
        ->getJson('/api/dashboard')
        ->assertOk()
        ->assertJsonPath('role', 'super_admin');
});
