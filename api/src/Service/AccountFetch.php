<?php

namespace App\Service;

use App\Config\RouteAction;
use App\Config\RouteType;
use App\Entity\Account;
use App\Repository\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class AccountFetch
{
    public function __construct(
        #[Autowire('%instanceHost%')]
        private string $instanceHost,
        private AccountRepository $accountRepository,
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @param string $usernameWithInstance even if the variable name has `withHost` it is not mandatory
     *
     * @return array{0: string, 1: string} Username and Host
     */
    public function parseUsernameWithInstance(string $usernameWithInstance): array
    {
        if (mb_substr_count(mb_ltrim($usernameWithInstance, '@'), '@') >= 2) {
            throw new BadRequestHttpException();
        }

        // https://regex101.com/r/ZwW3p1/2 TODO at some point we should allow more domain characters (same goes with username)
        if (false === preg_match('`^@?([a-zA-Z0-9]+)(?:@([a-zA-Z0-9_.-]+))?$`', $usernameWithInstance, $matches)) {
            throw new BadRequestHttpException();
        }

        return [
            $matches[1] ?? throw new BadRequestHttpException(),
            $matches[2] ?? $this->instanceHost,
        ];
    }

    /**
     * @param string $usernameWithInstance even if the variable name has `withHost` it is not mandatory
     */
    public function fetch(string $usernameWithInstance): Account
    {
        [$username, $instance] = $this->parseUsernameWithInstance($usernameWithInstance);
        $account = $this->accountRepository->findOneByUsernameAndInstance($username, $instance);
        if (!$account) {
            // TODO here we have to fetch from the webfinger of the other host
            // so we can get the public key and don't have to calculate uri
            $account = new Account();
            $account->username = $username;
            $account->instance = $instance;
            $account->uri = 'https://' . $instance . $this->urlGenerator->generate(RouteType::Profile->value . RouteAction::Get->value, [
                'username' => $username,
            ], UrlGeneratorInterface::ABSOLUTE_PATH);
            $this->entityManager->persist($account);
            $this->entityManager->flush();
        }

        return $account;
    }
}
