<?php

declare(strict_types=1);

namespace Foodticket\LaravelBirdDriver\Transport;

use Exception;
use Foodticket\LaravelBirdDriver\Contracts\BirdClientInterface;
use Foodticket\LaravelBirdDriver\Dto\PresignedUploadResponse;
use Foodticket\LaravelBirdDriver\Exceptions\AttachmentUploadFailedException;
use Foodticket\LaravelBirdDriver\Exceptions\BirdClientException;
use Foodticket\LaravelBirdDriver\Exceptions\BirdMailNotSentException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Psr\Http\Client\ClientExceptionInterface;
use Stringable;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Part\DataPart;

/**
 * https://docs.bird.com/api/channels-api/supported-channels/programmable-email/sending-messages
 */
class BirdMailTransport extends AbstractTransport implements Stringable
{
    public function __construct(private BirdClientInterface $client, private PendingRequest $uploader)
    {
        parent::__construct();

        $this->client = $client;
        $this->uploader = $uploader;

    }

    /**
     * @throws BirdMailNotSentException
     */
    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $data = [
            'receiver' => [
                'contacts' => $this->getReceivers($email)->all(),
            ],
        ];

        if ($contents = $this->getBody($email)) {
            $data['body'] = $contents;
        }

        if ($metadata = $this->getMetadata($email)) {
            $data['body']['html']['metadata'] = $metadata;
        }

        $attachments = $this->uploadAttachments($email);
        if ($attachments->isNotEmpty()) {
            $data['body']['html']['attachments'] = $attachments;
        }

        try {
            $response = $this->client->sendMail($data);
        } catch (Exception $exception) {
            throw new BirdMailNotSentException($exception->getMessage());
        }

        if ($response->successful() === false) {
            throw new BirdMailNotSentException();
        }
    }

    private function getReceivers(Email $email): Collection
    {
        return collect($email->getTo())->map(fn (Address $address) => $this->getContact($address));
    }

    /**
     * @return string[]
     */
    private function getContact(Address $address): array
    {
        return [
            'identifierKey' => 'emailaddress',
            'identifierValue' => $address->getAddress(),
        ];
    }

    /**
     * @throws BirdMailNotSentException
     * @return string[]
     */
    private function getBody(Email $email): array
    {
        if (! is_null($email->getHtmlBody())) {
            $contents = [
                'type' => 'html',
                'html' => [
                    'html' => $email->getHtmlBody(),
                ],
            ];
        } elseif (! is_null($email->getTextBody())) {
            $contents = [
                'type' => 'text',
                'text' => [
                    'text' => $email->getTextBody(),
                ],
            ];
        }

        if (empty($contents)) {
            throw new BirdMailNotSentException('Email body is empty.');
        }

        return $contents;
    }

    /**
     * @return string[]
     */
    private function getMetadata(Email $email): array
    {
        $metadata = [];

        if (! empty($email->getSubject())) {
            $metadata['subject'] = $email->getSubject();
        }

        if (! empty($email->getReplyTo())) {
            $metadata['headers']['reply-to'] = $this->getReplyTo($email);
        }

        if (! empty($email->getFrom()) && ! empty($email->getFrom()[0])) {
            $from = $email->getFrom()[0];

            $metadata['emailFrom'] = $this->getFrom($email);
        }

        return $metadata;
    }

    private function getReplyTo(Email $email): ?string
    {
        if (count($email->getReplyTo()) > 0) {
            $replyTo = $email->getReplyTo()[0];

            return $replyTo->getAddress();
        }

        return null;
    }

    /**
     * @return ?string[]
     */
    private function getFrom(Email $email): ?array
    {
        if (count($email->getFrom()) === 0) {
            return null;
        }

        $from = $email->getFrom()[0];

        return [
            'displayName' => $from->getName(),
            'username' => $from->getAddress(),
        ];
    }

    /**
     * @throws AttachmentUploadFailedException
     */
    private function uploadAttachments(Email $email): Collection
    {
        $attachments = collect();
        foreach ($email->getAttachments() as $attachment) {
            $presignedUploadResponse = $this->prepareUploadAttachment($attachment);
            $result = $this->uploadAttachment($attachment, $presignedUploadResponse);

            if (! $result->noContent()) {
                throw new AttachmentUploadFailedException();
            }

            $attachments->add([
                'mediaUrl' => $presignedUploadResponse->mediaUrl,
                'filename' => $this->getAttachmentName($attachment),
                'inline' => false,
            ]);
        }

        return $attachments;
    }

    /**
     * @throws BirdClientException
     * @throws AttachmentUploadFailedException
     */
    private function prepareUploadAttachment(DataPart $attachment): PresignedUploadResponse
    {
        try {
            $response = $this->client->createPresignedUploadUrl($this->getAttachmentContentType($attachment));
        } catch (ClientExceptionInterface $exception) {
            throw new BirdClientException($exception->getMessage());
        }

        if ($response->successful() === false) {
            throw new AttachmentUploadFailedException();
        }

        return PresignedUploadResponse::from($response->body());
    }

    /**
     * @throws BirdClientException
     */
    private function uploadAttachment(DataPart $attachment, PresignedUploadResponse $presignedUploadResponse): Response
    {
        $headers = collect(['Content-Type' => $presignedUploadResponse->uploadFormData->contentType]);
        $formParams = collect();
        foreach ($presignedUploadResponse->uploadFormData->toArray() as $name => $contents) {
            $formParams->add([
                'name' => $name,
                'contents' => $contents,
            ]);
        }

        $formParams->add([
            'name' => 'file',
            'contents' => $attachment->getBody(),
            'filename' => $attachment->getFilename(),
            'headers' => $headers->all(),
        ]);

        try {
            $response = $this->uploader->post(
                $presignedUploadResponse->uploadUrl,
                $formParams->all(),
            );
        } catch (ClientExceptionInterface $exception) {
            throw new BirdClientException($exception->getMessage());
        }

        return $response;
    }

    private function getAttachmentName(DataPart $dataPart): string
    {
        return $dataPart->getPreparedHeaders()->getHeaderParameter('Content-Disposition', 'filename');
    }

    private function getAttachmentContentType(Datapart $dataPart): string
    {
        return $dataPart->getMediaType().'/'.$dataPart->getMediaSubtype();
    }

    public function __toString(): string
    {
        return 'bird';
    }
}
