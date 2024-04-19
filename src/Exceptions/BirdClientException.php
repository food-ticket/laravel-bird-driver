<?php

declare(strict_types=1);

namespace Foodticket\LaravelBirdDriver\Exceptions;

use RuntimeException;

class BirdClientException extends RuntimeException
{
    protected $message = 'An unknown error has occurred.';
}
