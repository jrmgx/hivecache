<?php

declare(strict_types=1);

namespace App\ActivityPub\Controller;

use App\ActivityPub\Bundler\CreateActivityBundler;
use App\ActivityPub\Dto\OrderedCollection;
use App\Config\RouteAction;
use App\Config\RouteType;
use App\Entity\Account;
use App\Helper\PaginationHelper;
use App\Repository\BookmarkRepository;
use App\Response\ActivityPubResponseBuilder;
use App\Service\UrlGenerator;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/ap', name: RouteType::ActivityPub->value)]
class OutboxController extends AbstractController
{
    public function __construct(
        private readonly ActivityPubResponseBuilder $activityPubResponseBuilder,
        private readonly BookmarkRepository $bookmarkRepository,
        private readonly CreateActivityBundler $createActivityBundler,
        private readonly UrlGenerator $urlGenerator,
    ) {
    }

    #[Route(path: '/u/{username}/outbox', name: RouteAction::Outbox->value, methods: ['GET'])]
    public function outbox(
        #[MapEntity(mapping: ['username' => 'username'])] Account $account,
        #[MapQueryParameter(name: 'after')] ?string $after = null,
    ): JsonResponse {
        if (!$account->owner) {
            throw $this->createNotFoundException('Account has no owner.');
        }

        $qb = $this->bookmarkRepository->findByAccount($account, true);

        $totalItems = (int) (clone $qb)
            ->resetDQLPart('select')
            ->resetDQLPart('orderBy')
            ->select('COUNT(o.id)')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        if (!$after) {
            return $this->activityPubResponseBuilder->orderedCollection(RouteAction::Outbox, $account, $totalItems);
        }

        if (OrderedCollection::FIRST_KEY === $after) {
            $after = OrderedCollection::FIRST_VALUE;
        }
        $qb = PaginationHelper::applyPagination($qb, $after, 100);
        $bookmarks = $qb->getQuery()->getResult();

        $followers = [
            $this->urlGenerator->generate(RouteType::ActivityPub, RouteAction::Follower, ['username' => $account->username]),
        ];

        $activities = [];
        foreach ($bookmarks as $bookmark) {
            $activities[] = $this->createActivityBundler->bundleFromBookmark($bookmark, $followers);
        }

        $nextPageUrl = null;
        if (100 === \count($bookmarks)) {
            $lastBookmark = end($bookmarks);
            $nextPageUrl = $this->urlGenerator->generate(
                RouteType::ActivityPub,
                RouteAction::Outbox,
                ['username' => $account->username, 'after' => $lastBookmark->id]
            );
        }

        return $this->activityPubResponseBuilder->orderedCollectionPageWithActivities(
            RouteAction::Outbox,
            $account,
            $activities,
            $totalItems,
            $after,
            $nextPageUrl
        );
    }
}
