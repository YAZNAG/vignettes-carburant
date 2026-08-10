<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MotDePasseModifie extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre mot de passe a été modifié')
            ->greeting("Bonjour {$notifiable->prenom},")
            ->line('Le mot de passe de votre compte vient d\'être modifié.')
            ->line('Si vous n\'êtes pas à l\'origine de ce changement, contactez immédiatement un administrateur.')
            ->salutation('Gestion des vignettes carburant');
    }
}
