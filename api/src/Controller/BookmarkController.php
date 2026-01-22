<?php

namespace App\Controller;

use App\Config\RouteAction;
use App\Config\RouteType;
use App\Entity\Account;
use App\Entity\Bookmark;
use App\Helper\PaginationHelper;
use App\Repository\BookmarkRepository;
use App\Response\JsonResponseBuilder;
use App\Service\IndexActionUpdater;
use App\Service\InstanceTagService;
use App\Service\UrlGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;

abstract class BookmarkController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(PREFERRED_CLIENT)%')]
        protected readonly string $preferredClient,
        #[Autowire('%instanceHost%')]
        protected readonly string $instanceHost,
        protected readonly BookmarkRepository $bookmarkRepository,
        protected readonly EntityManagerInterface $entityManager,
        protected readonly JsonResponseBuilder $jsonResponseBuilder,
        protected readonly MessageBusInterface $messageBus,
        protected readonly IndexActionUpdater $indexActionUpdater,
        protected readonly InstanceTagService $instanceTagService,
        protected readonly UrlGenerator $urlGenerator,
    ) {
    }

    /**
     * @param list<string>  $groups
     * @param array<string> $params
     */
    public function collectionCommon(
        Account $account,
        ?string $tagQueryString,
        ?string $afterQueryString,
        array $groups,
        RouteType $routeType,
        RouteAction $routeAction,
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

        $qb = $this->bookmarkRepository->findByAccount($account, $onlyPublic);
        $qb = $this->bookmarkRepository->applyFilters($qb, $tagSlugs, $onlyPublic);

        return $this->responseFromQueryBuilder(
            $qb,
            $afterQueryString,
            $groups,
            $routeType,
            $routeAction,
            $params,
            $tagSlugs,
            $resultPerPage
        );
    }

    /**
     * @param list<string>  $groups
     * @param array<string> $params
     * @param array<string> $tagSlugs
     */
    protected function responseFromQueryBuilder(
        QueryBuilder $qb,
        ?string $afterQueryString,
        array $groups,
        RouteType $routeType,
        RouteAction $routeAction,
        array $params = [],
        array $tagSlugs = [],
        int $resultPerPage = 24,
    ): JsonResponse {
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

        $qb = PaginationHelper::applyPagination($qb, $afterQueryString, $resultPerPage);
        /** @var array<Bookmark> $bookmarks */
        $bookmarks = $qb->getQuery()->getResult();
        $lastBookmark = end($bookmarks);

        $nextPage = false;
        if ($lastBookmark) {
            $params['after'] = $lastBookmark->id;
            if (\count($tagSlugs)) {
                $params['tags'] = implode(',', $tagSlugs);
            }
            $nextPage = $this->urlGenerator->generate($routeType, $routeAction, $params);
        }

        return $this->jsonResponseBuilder->collection(
            $bookmarks, $groups, [
                'prevPage' => null, // We don't provide a previous page on bookmarks
                'nextPage' => $nextPage,
                'total' => $count,
            ]);
    }
}
