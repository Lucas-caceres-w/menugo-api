<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionRenewalDue extends Notification
{
    use Queueable;

    public function __construct(private readonly Subscription $subscription)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tu plan de MenuGo está por finalizar')
            ->greeting("Hola {$notifiable->name}")
            ->line("Tu plan {$this->subscription->plan} finaliza el {$this->subscription->ends_at?->format('d/m/Y')}.")
            ->line($this->subscription->auto_renew
                ? 'La renovación automática está autorizada y Mercado Pago procesará el próximo cobro.'
                : 'No tienes renovación automática autorizada. Renueva tu plan desde MenuGo antes de esa fecha.')
            ->action('Ver mi suscripción', config('app.frontend_url') . '/dashboard/subscription');
    }
}
