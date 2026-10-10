<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AuthService;

class AuthController extends Controller
{
    public function login(Request $request, AuthService $authService)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $token = $authService->login(
            $validated['email'],
            $validated['password']
        );

        return response()->json([
            'token' => $token,
        ]);
    }
}
