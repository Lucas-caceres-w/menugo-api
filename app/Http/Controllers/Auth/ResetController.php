<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class ResetController extends Controller
{
    /**
     * Enviar email de reseteo de contraseña
     */
    public function sendResetLink(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
            ]);

            $status = Password::sendResetLink(
                $request->only('email')
            );

            if ($status !== Password::RESET_LINK_SENT) {
                return response()->json([
                    'message' => __($status),
                ], 400);
            }

            return response()->json([
                'message' => 'Email de recuperación enviado',
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Error al enviar email de recuperación',
            ], 500);
        }
    }

    /**
     * Resetear contraseña
     */
    public function reset(Request $request)
    {
        try {
            $request->validate([
                'token' => 'required|string',
                'email' => 'required|email',
                'password' => 'required|string|min:8|confirmed',
            ]);

            $status = Password::reset(
                $request->only(
                    'email',
                    'password',
                    'password_confirmation',
                    'token'
                ),
                function ($user, $password) {
                    $user->forceFill([
                        'password' => Hash::make($password),
                        'remember_token' => Str::random(60),
                    ])->save();

                    // Revocar tokens existentes (opcional pero recomendado)
                    $user->tokens()->delete();
                }
            );

            if ($status !== Password::PASSWORD_RESET) {
                return response()->json([
                    'message' => __($status),
                ], 400);
            }

            return response()->json([
                'message' => 'Contraseña actualizada correctamente',
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Error al resetear contraseña',
            ], 500);
        }
    }
}
