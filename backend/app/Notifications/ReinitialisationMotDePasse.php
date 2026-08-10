<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReinitialisationMotDePasse extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $token,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim(config('app.frontend_url'), '/')
            .'/reinitialiser-mot-de-passe?token='.$this->token
            .'&email='.urlencode($notifiable->email);

        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe')
            ->greeting("Bonjour {$notifiable->prenom},")
            ->line('Vous recevez cet e-mail car une réinitialisation de mot de passe a été demandée pour votre compte.')
            ->action('Définir un nouveau mot de passe', $url)
            ->line('Ce lien est valable 30 minutes et ne peut être utilisé qu\'une seule fois.')
            ->line('Si vous n\'êtes pas à l\'origine de cette demande, ignorez cet e-mail.')
            ->salutation('Gestion des vignettes carburant');
    }
}
