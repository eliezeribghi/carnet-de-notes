<?php
// =============================================================================
// app/Notifications/ResetPasswordNotification.php
//
// Email de réinitialisation, commun client B2B + backoffice.
// Le lien pointe vers le BON front selon le rôle du destinataire :
//   - admin / employee → front backoffice
//   - client (défaut)  → front client SvelteKit
// =============================================================================

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Choix du front cible selon le type de compte.
        $isBackoffice = in_array($notifiable->role, ['admin', 'employee'], true);

        $base = rtrim(
            $isBackoffice ? config('app.backoffice_url') : config('app.frontend_url'),
            '/'
        );

        $url = "{$base}/reset-password?token={$this->token}&email="
             . urlencode($notifiable->getEmailForPasswordReset());

        $expire = config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe — Ness Paris')
            ->greeting('Bonjour,')
            ->line('Vous recevez cet email car une réinitialisation de mot de passe a été demandée pour votre compte.')
            ->action('Réinitialiser mon mot de passe', $url)
            ->line("Ce lien expirera dans {$expire} minutes.")
            ->line("Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet email.")
            ->salutation('— L\'équipe Ness Paris');
    }
}