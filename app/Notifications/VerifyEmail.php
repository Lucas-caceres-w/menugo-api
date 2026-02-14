<?php
// app/Notifications/VerifyEmail.php
namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as VerifyEmailBase;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmail extends VerifyEmailBase
{
            public function toMail($notifiable)
            {
                        $url = $this->verificationUrl($notifiable);

                        return (new MailMessage)->subject('Confirma tu correo en MenuGo')
                                    ->view('email', [
                                                'user' => $notifiable,
                                                'url' => $url,
                                    ]);
            }
}
