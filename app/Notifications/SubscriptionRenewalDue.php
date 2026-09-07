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
            ->line("Tu plan {$this->subscription->plan} finaliza el {$this->subscription->ends_at?->format('d/m/Y')}. Tendrás tres días adicionales para renovarlo.")
            ->line('Renueva tu plan desde MenuGo cuando finalice para continuar utilizando el servicio.')
            ->action('Ver mi suscripción', config('app.frontend_url') . '/dashboard/subscription');
    }
}
