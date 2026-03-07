<?php

namespace App\Api\Helper;

use App\Entity\Account;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ClientUrlHelper
{
    public function __construct(
        #[Autowire('%env(PREFERRED_CLIENT)%')]
        private string $preferredClient,
    ) {
    }

    public function profile(Account $account): string
    {
        return $this->base($account);
    }

    public function bookmarks(Account $account, ?string $tagQueryString = null): string
    {
        $url = $this->base($account);
        if ($tagQueryString) {
            $url .= '?tags=' . urlencode($tagQueryString);
        }

        return $url;
    }

    public function bookmark(Account $account, string $id): string
    {
        return $this->base($account) . '/bookmarks/' . $id;
    }

    public function tags(Account $account): string
    {
        return $this->base($account) . '/tags';
    }

    public function tagFilter(Account $account, string $slug): string
    {
        return $this->base($account) . '?tags=' . urlencode($slug);
    }

    private function base(Account $account): string
    {
        $profileIdentifier = $account->username . '@' . $account->instance;

        return mb_rtrim($this->preferredClient, '/') . '/social/' . $profileIdentifier;
    }
}
