<?php

declare(strict_types=1);

namespace App\Integrations\Bird;

use App\Integrations\Bird\Clients\BirdClient;
use App\Integrations\Bird\Contracts\BirdClientInterface;
use App\Integrations\Bird\Transport\BirdMailTransport;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

final class BirdServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
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
