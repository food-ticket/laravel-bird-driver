<?php

declare(strict_types=1);

namespace Foodticket\LaravelBirdDriver\Contracts;

use Illuminate\Http\Client\Response;

interface BirdClientInterface
{
    public function createPresignedUploadUrl(string $contentType): Response;

    public function sendMail(array $payload): Response;
}
