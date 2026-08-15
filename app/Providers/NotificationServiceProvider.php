<?php

declare(strict_types=1);

namespace App\Providers;

use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Notification::resolved(function (ChannelManager $service) {
            $service->extend('whatsapp', fn () => $this->app->make(WhatsAppChannel::class));
        });
    }
}
