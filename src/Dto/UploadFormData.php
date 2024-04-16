<?php

declare(strict_types=1);

namespace Foodticket\LaravelBirdDriver\Dto;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;

class UploadFormData extends Data
{
    public function __construct(
        #[MapName('Content-Type')]
        readonly public string $contentType,
        readonly public string $acl,
        readonly public string $bucket,
        readonly public string $key,
        readonly public string $policy,
        #[MapName('x-amz-algorithm')]
        readonly public string $xAmzAlgorithm,
        #[MapName('x-amz-credential')]
        readonly public string $xAmzCredential,
        #[MapName('x-amz-date')]
        readonly public string $xAmzDate,
        #[MapName('x-amz-meta-allowed-paths')]
        readonly public string $xAmzMetaAllowedPaths,
        #[MapName('x-amz-meta-channel-id')]
        readonly public string $xAmzMetaChannelId,
        #[MapName('x-amz-security-token')]
        readonly public string $xAmzSecurityToken,
        #[MapName('x-amz-signature')]
        readonly public string $xAmzSignature,
    ) {
    }
}
