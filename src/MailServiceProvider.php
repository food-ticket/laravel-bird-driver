<?php
namespace Foodticket\LaravelBirdDriver;

class MailServiceProvider extends \Illuminate\Mail\MailServiceProvider
{
    public function register()
    {
        parent::register();

        $this->app->register(BirdServiceProvider::class);
    }
}
