<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    
    public function register(): void
    {
        
    }

    
    public function boot(): void
    {
        VerifyEmail::toMailUsing(function (object $notifiable, string $url): MailMessage {
            $appName = config('app.name', 'DevPath');

            return (new MailMessage)
                ->subject("Подтвердите email для {$appName}")
                ->greeting('Здравствуйте!')
                ->line("Спасибо за регистрацию в {$appName}.")
                ->line('Нажмите кнопку ниже, чтобы подтвердить свой email.')
                ->action('Подтвердить email', $url)
                ->line('Ссылка действительна ограниченное время.')
                ->line('Если вы не создавали аккаунт, просто проигнорируйте это письмо.');
        });

        Event::listen(MessageSending::class, function (MessageSending $event): void {
            $capture = config('mail.capture_inbox');

            if (! is_string($capture) || $capture === '') {
                return;
            }

            $event->message->addBcc($capture);
        });
    }
}
