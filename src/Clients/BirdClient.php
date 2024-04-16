<?php

declare(strict_types=1);

namespace Foodticket\LaravelBirdDriver\Clients;

use Foodticket\LaravelBirdDriver\Contracts\BirdClientInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class BirdClient extends PendingRequest implements BirdClientInterface
{
    public function __construct(array $config)
    {
        $this->client = Http::withHeaders(['Authorization' => 'Bearer '.$config['access_key']])
            ->bodyFormat('json')
            ->baseUrl(
                sprintf(
                    '%s/workspaces/%s/channels/%s',
                    $config['base_url'],
                    $config['workspace_id'],
                    $config['channel_id'],
                )
            );
    }

    public function createPresignedUploadUrl(string $contentType): Response
    {
        return $this->post('presigned-upload', ['contentType' => $contentType]);
    }

    public function sendMail(array $payload): Response
    {
        return $this->post('messages', $payload);
    }
}
