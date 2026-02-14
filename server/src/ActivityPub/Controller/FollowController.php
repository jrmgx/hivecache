<?php

declare(strict_types=1);

namespace App\ActivityPub\Controller;

use App\ActivityPub\Dto\OrderedCollection;
use App\Api\Config\RouteAction;
use App\Api\Config\RouteType;
use App\Api\Helper\PaginationHelper;
use App\Api\Response\ActivityPubResponseBuilder;
use App\Api\UrlGenerator;
use App\Entity\Account;
use App\Repository\FollowerRepository;
use App\Repository\FollowingRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/ap', name: RouteType::ActivityPub->value)]
class FollowController extends AbstractController
{
    public function __construct(
        private readonly ActivityPubResponseBuilder $activityPubResponseBuilder,
        private readonly FollowerRepository $followerRepository,
        private readonly FollowingRepository $followingRepository,
        private readonly UrlGenerator $urlGenerator,
    ) {
    }

    #[Route(path: '/u/{username}/followers', name: RouteAction::Follower->value, methods: ['GET'])]
    public function followers(
        #[MapEntity(mapping: ['username' => 'username'])] Account $account,
        #[MapQueryParameter(name: 'after')] ?string $after = null,
    ): JsonResponse {
        return $this->follow(RouteAction::Follower, $account, $after);
    }

    #[Route(path: '/u/{username}/following', name: RouteAction::Following->value, methods: ['GET'])]
    public function following(
        #[MapEntity(mapping: ['username' => 'username'])] Account $account,
        #[MapQueryParameter(name: 'after')] ?string $after = null,
    ): JsonResponse {
        return $this->follow(RouteAction::Following, $account, $after);
    }

    private function follow(
        RouteAction $routeAction,
        Account $account,
        ?string $after,
    ): JsonResponse {
        $owner = $account->owner ??
            throw $this->createNotFoundException('Account has no owner.');

        $repository = RouteAction::Follower === $routeAction ?
            $this->followerRepository : $this->followingRepository;

        $qb = $repository->findByOwner($owner);

        $totalItems = (int) (clone $qb)
            ->resetDQLPart('select')
            ->resetDQLPart('orderBy')
            ->select('COUNT(o.id)')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        if (!$after) {
            return $this->activityPubResponseBuilder->orderedCollection($routeAction, $account, $totalItems);
        }

        if (OrderedCollection::FIRST_KEY === $after) {
            $after = OrderedCollection::FIRST_VALUE;
        }
        $qb = PaginationHelper::applyPagination($qb, $after, 100);
        $followings = $qb->getQuery()->getResult();

        $accountUris = array_map(fn ($following) => $following->account->uri, $followings);

        $nextPageUrl = null;
        if (100 === \count($followings)) {
            $lastFollowing = end($followings);
            $nextPageUrl = $this->urlGenerator->generate(
                RouteType::ActivityPub,
                $routeAction,
                ['username' => $account->username, 'after' => $lastFollowing->id]
            );
        }

        return $this->activityPubResponseBuilder->orderedCollectionPage(
            $routeAction,
            $account,
            $accountUris,
            $totalItems,
            $after,
            $nextPageUrl
        );
    }
}
