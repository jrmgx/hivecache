<?php

namespace App\ActivityPub\MessageHandler;

use App\ActivityPub\AccountFetch;
use App\ActivityPub\Exception\SignatureException;
use App\ActivityPub\Message\SignedMessageInterface;
use App\ActivityPub\SignatureHelper;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

abstract readonly class AbstractSignedMessageHandler
{
    public function __construct(
        #[Autowire('%instanceHost%')]
        private string $instanceHost,
        #[Autowire('%env(APP_ENV)%')]
        private string $appEnv,
        private AccountFetch $accountFetch,
    ) {
    }

    protected function validateMessageOrThrow(SignedMessageInterface $message): void
    {
        $headers = $message->headers;
        // Here we fake header/host so they match our dev instance (when behind a tunnel for example)
        if ('prod' !== $this->appEnv) {
            $headers['host'] = [$this->instanceHost];
        }

        $signatureHeader = $headers['signature'][0]
            ?? throw new UnrecoverableMessageHandlingException('Missing signature header.');

        try {
            $signatureData = SignatureHelper::parseSignatureHeader($signatureHeader);
        } catch (SignatureException $e) {
            throw new UnrecoverableMessageHandlingException('Invalid signature header: ' . $e->getMessage());
        }

        $actorUri = explode('#', $signatureData['keyId'], 2)[0];
        $account = $this->accountFetch->fetchFromUri($actorUri);

        // Normalize headers for verification (lowercase keys, array values)
        $normalizedHeaders = [];
        foreach ($headers as $headerName => $headerValue) {
            $normalizedName = mb_strtolower($headerName);
            $normalizedHeaders[$normalizedName] = \is_array($headerValue) ? $headerValue : [$headerValue];
        }

        $isValid = SignatureHelper::verify(
            (string) parse_url($message->uri, \PHP_URL_PATH),
            $account->publicKey
                ?? throw new UnrecoverableMessageHandlingException('Account has no public key: ' . $actorUri),
            $signatureData,
            $normalizedHeaders,
            $message->body
        );

        if (!$isValid) {
            throw new UnrecoverableMessageHandlingException('Signature verification failed for actor: ' . $actorUri);
        }
    }
}
