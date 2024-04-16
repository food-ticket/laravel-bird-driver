<?php

declare(strict_types=1);

namespace Foodticket\LaravelBirdDriver;

use Foodticket\LaravelBirdDriver\Clients\BirdClient;
use Foodticket\LaravelBirdDriver\Contracts\BirdClientInterface;
use Foodticket\LaravelBirdDriver\Transport\BirdMailTransport;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

final class BirdServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     */
    public function register(): void
    {
        $this->app->singleton(BirdClientInterface::class, BirdClient::class);

        $this->mergeConfigFrom(__DIR__ . '/config', 'services.bird.mail');

        $this->app->afterResolving(MailManager::class, function (MailManager $mailManager) {
            $mailManager->extend('bird', function ($config) {
                if (! isset($config['access_key'])) {
                    $config = $this->app['config']->get('services.bird.mail', []);
                }

                $birdClient = new BirdClient($config);
                $uploader = Http::asMultipart();

                return new BirdMailTransport($birdClient, $uploader);
            });
        });
    }
}
