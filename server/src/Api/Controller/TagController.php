<?php

namespace App\Api\Controller;

use App\Api\InstanceTagService;
use App\Api\Response\JsonResponseBuilder;
use App\Api\UrlGenerator;
use App\Entity\User;
use App\Repository\InstanceTagRepository;
use App\Repository\UserTagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;

abstract class TagController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(PREFERRED_CLIENT)%')]
        protected readonly string $preferredClient,
        protected readonly UserTagRepository $userTagRepository,
        protected readonly InstanceTagService $instanceTagService,
        protected readonly InstanceTagRepository $instanceTagRepository,
        protected readonly EntityManagerInterface $entityManager,
        protected readonly JsonResponseBuilder $jsonResponseBuilder,
        protected readonly UrlGenerator $urlGenerator,
    ) {
    }

    /**
     * @param list<string> $groups
     */
    public function collectionCommon(User $user, array $groups, bool $onlyPublic): JsonResponse
    {
        $tags = $this->userTagRepository->findByOwner($user, onlyPublic: $onlyPublic)
            ->getQuery()
            ->getResult()
        ;

        return $this->jsonResponseBuilder->collection(
            $tags, $groups, ['total' => \count($tags)]
        );
    }
}
