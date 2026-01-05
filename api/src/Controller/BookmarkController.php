<?php

namespace App\Controller;

use App\Config\RouteAction;
use App\Config\RouteType;
use App\Entity\Bookmark;
use App\Entity\User;
use App\Repository\BookmarkRepository;
use App\Response\JsonResponseBuilder;
use App\Service\IndexActionUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

abstract class BookmarkController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(PREFERRED_CLIENT)%')]
        protected readonly string $preferredClient,
        protected readonly BookmarkRepository $bookmarkRepository,
        protected readonly EntityManagerInterface $entityManager,
        protected readonly JsonResponseBuilder $jsonResponseBuilder,
        protected readonly MessageBusInterface $messageBus,
        protected readonly IndexActionUpdater $indexActionUpdater,
    ) {
    }

    /**
     * @param list<string>  $groups
     * @param array<string> $params
     */
    public function collectionCommon(
        User $user,
        ?string $tagQueryString,
        ?string $searchQueryString,
        ?string $afterQueryString,
        array $groups,
        RouteType $routeType,
        array $params = [],
        bool $onlyPublic = true,
        int $resultPerPage = 24,
    ): JsonResponse {
        $tagSlugs = [];
        if ($tagQueryString) {
            $tagSlugs = explode(',', $tagQueryString);
            $tagSlugs = array_map(fn (string $t) => mb_trim($t), $tagSlugs);
            $tagSlugs = array_filter($tagSlugs, fn (string $t) => '' !== $t);
        }

        $qb = $this->bookmarkRepository->findByOwner($user, $onlyPublic);
        $qb = $this->bookmarkRepository->applyFilters($qb, $tagSlugs, $onlyPublic);

        // TODO make the count work when multiple tags (with join etc)
        $count = null;
        if (0 === \count($tagSlugs)) {
            $count = (clone $qb)
                ->resetDQLPart('select')
                ->resetDQLPart('orderBy')
                ->select('COUNT(o.id)')
                ->getQuery()
                ->getSingleColumnResult()[0] ?? null
            ;
        }

        $qb = $this->bookmarkRepository->applyPagination($qb, $afterQueryString, $resultPerPage);
        /** @var array<Bookmark> $bookmarks */
        $bookmarks = $qb->getQuery()->getResult();
        $lastBookmark = end($bookmarks);

        $nextPage = false;
        if ($lastBookmark) {
            $params['after'] = $lastBookmark->id;
            if ($tagQueryString) {
                $params['tags'] = $tagQueryString;
            }
            $nextPage = $this->generateUrl($routeType->value . RouteAction::Collection->value, $params, UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return $this->jsonResponseBuilder->collection(
            $bookmarks, $groups, [
                'prevPage' => null, // We don't provide a previous page on bookmarks
                'nextPage' => $nextPage,
                'total' => $count,
            ]);
    }
}
