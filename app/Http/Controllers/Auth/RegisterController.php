<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class RegisterController extends Controller

{
    public function register(Request $request)
    {
        try {

            $data = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8',
            ]);

            // Verificar si el email ya existe
            if (User::where('email', $data['email'])->exists()) {
                return response()->json([
                    'message' => 'El email ya está en uso',
                    'errors' => ['email' => ['El email ingresado ya está registrado']]
                ], 422);
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            // Enviar verificación (no bloqueante)
            event(new Registered($user));

            $token = $user->createToken('spa')->plainTextToken;

            if (!$user->subscription) {
                Subscription::createTrialForUser($user);
            }

            return response()->json([
                'message' => 'Usuario creado',
                'token'   => $token,
                'email_verification_sent' => true
            ], 201);
        } catch (Throwable $e) {
            report($e);
            logger($e);

            return response()->json([
                'message' => 'Error al registrar usuario'
            ], 500);
        }
    }
}
