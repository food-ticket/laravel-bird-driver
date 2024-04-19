<?php

namespace Tests\Unit\Transport;

use Foodticket\LaravelBirdDriver\Contracts\BirdClientInterface;
use Foodticket\LaravelBirdDriver\Transport\BirdMailTransport;
use Illuminate\Http\Client\PendingRequest;
use Faker;
use Foodticket\LaravelBirdDriver\Exceptions\BirdMailNotSentException;
use Illuminate\Http\Client\Response;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class BirdMailTransportTest extends TestCase
{
    private BirdMailTransport $transport;
    private ReflectionClass $reflection;
    private BirdClientInterface $birdClient;

    protected function setUp(): void
    {
        // Mock BirdClientInterface
        $this->birdClient = Mockery::mock(BirdClientInterface::class);
        $this->transport = new BirdMailTransport(
            $this->birdClient,
            // Mock PendingRequest
            Mockery::mock(PendingRequest::class)
        );

        $this->reflection = new \ReflectionClass($this->transport);
    }

    /**
     * @covers \Foodticket\LaravelBirdDriver\Transport\BirdMailTransport::doSend()
     */
    public function test_doSendCannotSendMailDueToResponseNotSuccessful(): void
    {
        $faker = Faker\Factory::create();
        $message = new SentMessage(
            // Mock Email
            (new Email())
            ->from(new Address($faker->safeEmail(), $faker->name()))
            ->to(new Address($faker->safeEmail()))
            ->subject('Test Subject')
            ->text('Test Body'),
            Mockery::mock(Envelope::class)
        );

        $this->birdClient->shouldReceive('sendMail')->once()->andReturn(
            Mockery::mock(Response::class)->shouldReceive('successful')->andReturn(false)->getMock()
        );

        $method = $this->reflection->getMethod('doSend');
        $method->setAccessible(true);

        $this->expectException(BirdMailNotSentException::class);
        $method->invoke($this->transport, $message);

        $this->assertTrue(true);
    }
}
