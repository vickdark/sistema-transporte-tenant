<?php

namespace App\Notifications;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Invitación a un usuario de un tenant para que defina su contraseña. El enlace apunta al
 * dominio principal del tenant y deja de servir en cuanto el usuario cambia su contraseña.
 */
class TenantUserInvitation extends Notification
{
    use Queueable;

    public const EXPIRATION_DAYS = 7;

    public function __construct(public Tenant $tenant) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Invitación a {$this->tenant->name}")
            ->greeting("Hola, {$notifiable->name}")
            ->line("Tienes una cuenta en {$this->tenant->name}. Tu usuario es: {$notifiable->username}")
            ->line('Define tu contraseña para empezar a usar el sistema.')
            ->action('Definir contraseña', $this->invitationUrl($notifiable))
            ->line('Este enlace vence en '.self::EXPIRATION_DAYS.' días.');
    }

    public function invitationUrl(User $notifiable): string
    {
        $path = URL::temporarySignedRoute(
            'invitation.show',
            now()->addDays(self::EXPIRATION_DAYS),
            ['user' => $notifiable->getKey(), 'tenant' => $this->tenant->getTenantKey(), 'hash' => $notifiable->invitationHash()],
            absolute: false,
        );

        return $this->tenant->url($path);
    }
}
