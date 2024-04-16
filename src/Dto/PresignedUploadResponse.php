<?php

declare(strict_types=1);

namespace App\Integrations\Bird\Dto;

use Spatie\LaravelData\Data;

class PresignedUploadResponse extends Data
{
    public function __construct(
        public string $mediaUrl,
        public string $uploadUrl,
        public string $uploadMethod,
        public UploadFormData $uploadFormData,
    ) {
    }
}
