<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Throwable;

class VerificationController extends Controller
{
    /**
     * Verificar email (link del mail)
     */
    public function verify(EmailVerificationRequest $request)
    {
        try {
            if ($request->user()->hasVerifiedEmail()) {
                return redirect(config('app.frontend_url') . '/verify?status=already');
            }

            $request->fulfill();

            return redirect(config('app.frontend_url') . '/verify?status=success');
        } catch (Throwable $e) {
            return redirect(config('app.frontend_url') . '/verify?status=error');
        }
    }

    /**
     * Reenviar email de verificación
     */
    public function resend(Request $request)
    {
        try {
            if ($request->user()->hasVerifiedEmail()) {
                return response()->json([
                    'message' => 'El email ya está verificado',
                ], 400);
            }

            $request->user()->sendEmailVerificationNotification();

            return response()->json([
                'message' => 'Email de verificación reenviado',
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Error al reenviar email de verificación',
            ], 500);
        }
    }
}
