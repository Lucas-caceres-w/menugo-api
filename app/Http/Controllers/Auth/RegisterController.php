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

            DB::beginTransaction();
            
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8',
            ]);

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            try {
                event(new Registered($user));
            } catch (Throwable $mailError) {

                logger('Error enviando email de verificación', [
                    'user_id' => $user->id,
                    'error'   => $mailError->getMessage(),
                ]);

                DB::rollBack();

                return response()->json([
                    'message' => 'No se pudo enviar el email de verificación. Verifica que el correo sea válido.',
                    'code' => 'EMAIL_VERIFICATION_FAILED'
                ], 500);
            }

            $token = $user->createToken('spa')->plainTextToken;

            if (!$user->subscription) {
                Subscription::createTrialForUser($user);
            }

            return response()->json([
                'message' => 'Usuario creado',
                'token' => $token
            ], 201);
        } catch (Throwable $e) {
            report($e);
            logger($e->getMessage());
            return response()->json([
                'message' => 'Error al registrar usuario'
            ], 500);
        }
    }
}
