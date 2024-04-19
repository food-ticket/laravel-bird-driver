<?php

declare(strict_types=1);

namespace Foodticket\LaravelBirdDriver;

class MailServiceProvider extends \Illuminate\Mail\MailServiceProvider
{
    public function register(): void
    {
        parent::register();

        $this->app->register(BirdServiceProvider::class);
    }
}
