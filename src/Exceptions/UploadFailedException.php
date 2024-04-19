<?php

declare(strict_types=1);

namespace Foodticket\LaravelBirdDriver\Exceptions;

use RuntimeException;

class UploadFailedException extends RuntimeException
{
    protected $message = 'Upload of attachment to S3 bucket has failed.';
}
