<?php

namespace App\ActivityPub\MessageHandler;

use App\ActivityPub\AccountFetch;
use App\ActivityPub\Message\SendMessage;
use App\ActivityPub\SignatureHelper;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final readonly class SendMessageHandler
{
    public function __construct(
        #[Autowire('@activity_pub.client')]
        private HttpClientInterface $httpClient,
        private AccountFetch $accountFetch,
    ) {
    }

    public function __invoke(SendMessage $message): void
    {
        $actorAccount = $this->accountFetch->fetchFromUri($message->accountUri);

        $signatureHeaders = SignatureHelper::build(
            url: $message->url,
            keyId: $actorAccount->keyId,
            privateKey: $actorAccount->privateKey
                     ?? throw new UnrecoverableMessageHandlingException('No private key for actor.'),
            payload: $message->payload,
        );

        $response = $this->httpClient->request('POST', $message->url, [
            'headers' => $signatureHeaders,
            'body' => $message->payload,
        ]);

        if ($response->getStatusCode() >= 400) {
            throw new \LogicException('Error when sending the Follow Activity: ' . $response->getContent(false));
        }
    }
}
