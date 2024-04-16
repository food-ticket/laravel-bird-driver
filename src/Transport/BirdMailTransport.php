<?php

namespace App\Integrations\Bird\Transport;

use App\Integrations\Bird\Contracts\BirdClientInterface;
use App\Integrations\Bird\Dto\PresignedUploadResponse;
use App\Integrations\Bird\Exceptions\BirdException;
use Aws\S3\Exception\S3Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Stringable;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * https://docs.bird.com/api/channels-api/supported-channels/programmable-email/sending-messages
 */
class BirdMailTransport extends AbstractTransport implements Stringable
{
    private BirdClientInterface $client;
    private PendingRequest $uploader;

    public function __construct(BirdClientInterface $client, PendingRequest $uploader)
    {
        $this->client = $client;
        $this->uploader = $uploader;

        parent::__construct();
    }

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
        if ($attachments->isEmpty() === false) {
            $data['body']['html']['attachments'] = $attachments;
        }

        $response = $this->client->sendMail($data);
        // what to do with the response?
        // do we store all responses?
        // what do we do with emails where attachments failed to upload
        // uploadAttachment() is actually an S3 bucket upload, maybe use S3Client
    }

    private function getReceivers(Email $email): Collection
    {
        $contacts = collect();
        foreach ($email->getTo() as $address) {
            $contact = $this->getContact($address);

            $contacts->push($contact);
        }

        return $contacts;
    }

    private function getContact(Address $address): array
    {
        return [
            'identifierKey' => 'emailaddress',
            'identifierValue' => $address->getAddress(),
        ];
    }

    /**
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
            throw new BirdException('Email body is empty. Email has not been sent.');
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
        if (count($email->getFrom()) > 0) {
            $from = $email->getFrom()[0];

            $fromData = [
                'username' => $from->getAddress(),
            ];

            if ($fromData) {
                $fromData['displayName'] = $from->getName();
            }

            return $fromData;
        }

        return null;
    }

    private function uploadAttachments(Email $email): Collection
    {
        $attachments = collect();
        foreach ($email->getAttachments() as $attachment) {
            try {
                $presignedUploadResponse = $this->prepareUploadAttachment($attachment);
                $result = $this->uploadAttachment($attachment, $presignedUploadResponse);
            } catch (TransportExceptionInterface $e) {
                // presigned upload request failed
            } catch (S3Exception $e) {
                // do something with presignedUploadResponse
            }

            if (!$result->noContent()) {
                // do something with presignedUploadResponse?
                continue;
            };

            $attachments->add([
                'mediaUrl' => $presignedUploadResponse->mediaUrl,
                'filename' => $this->getAttachmentName($attachment),
                'inline' => false,
            ]);
        }

        return $attachments;
    }

    private function prepareUploadAttachment(DataPart $attachment): ?PresignedUploadResponse
    {
        $response = $this->client->createPresignedUploadUrl($this->getAttachmentContentType($attachment));

        return PresignedUploadResponse::from($response->body());
    }

    private function uploadAttachment(DataPart $attachment, PresignedUploadResponse $presignedUploadResponse): Response
    {
        $headers = collect(['Content-Type' => $presignedUploadResponse->uploadFormData->contentType]);
        $formParams = collect();
        foreach ($presignedUploadResponse->uploadFormData->toArray() as $name => $contents) {
            $formParams->add([
                'name' => $name,
                'contents' => $contents
            ]);
        }

        $formParams->add([
            'name' => 'file',
            'contents' => $attachment->getBody(),
            'filename' => $attachment->getFilename(),
            'headers' => $headers->all(),
        ]);

        return $this->uploader->post(
            $presignedUploadResponse->uploadUrl,
            $formParams->all(),
        );
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
