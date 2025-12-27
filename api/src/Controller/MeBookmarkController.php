<?php

namespace App\Controller;

use App\Config\RouteAction;
use App\Config\RouteType;
use App\Entity\Bookmark;
use App\Entity\User;
use App\Security\Voter\BookmarkVoter;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\ORMInvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/api/users/me/bookmarks', name: RouteType::MeBookmarks->value)]
final class MeBookmarkController extends BookmarkController
{
    #[Route(path: '', name: RouteAction::Collection->value, methods: ['GET'])]
    public function collection(
        #[CurrentUser] User $user,
        #[MapQueryParameter(name: 'tags')] ?string $tagQueryString = null,
        #[MapQueryParameter(name: 'q')] ?string $searchQueryString = null,
        #[MapQueryParameter(name: 'after')] ?string $afterQueryString = null,
    ): JsonResponse {
        return $this->collectionCommon(
            $user,
            $tagQueryString,
            $searchQueryString,
            $afterQueryString,
            ['bookmark:owner', 'tag:owner'],
            RouteType::MeBookmarks,
            onlyPublic: false
        );
    }

    #[Route(path: '', name: RouteAction::Create->value, methods: ['POST'])]
    public function create(
        #[CurrentUser] User $user,
        #[MapRequestPayload(
            serializationContext: ['groups' => ['bookmark:create']],
            validationGroups: ['Default'],
        )]
        Bookmark $bookmark,
    ): JsonResponse {
        // Find previous version and outdate it
        $existingBookmark = $this->bookmarkRepository->findLastOneByOwnerAndUrl($user, $bookmark->url)
            ->getQuery()->getOneOrNullResult()
        ;
        if ($existingBookmark) {
            $existingBookmark->outdated = true;
        }

        $bookmark->owner = $user;

        try {
            $this->entityManager->persist($bookmark);
            $this->entityManager->flush();
        } catch (ORMInvalidArgumentException|ORMException $e) {
            throw new UnprocessableEntityHttpException(previous: $e);
        }

        return $this->jsonResponseBuilder->single($bookmark, ['bookmark:owner', 'tag:owner']);
    }

    #[Route(path: '/{id}', name: RouteAction::Get->value, methods: ['GET'])]
    #[IsGranted(attribute: BookmarkVoter::OWNER, subject: 'bookmark', statusCode: Response::HTTP_NOT_FOUND)]
    public function get(
        Bookmark $bookmark,
    ): JsonResponse {
        return $this->jsonResponseBuilder->single($bookmark, ['bookmark:owner', 'tag:owner']);
    }

    #[Route(path: '/{id}/history', name: RouteAction::History->value, methods: ['GET'])]
    #[IsGranted(attribute: BookmarkVoter::OWNER, subject: 'bookmark', statusCode: Response::HTTP_NOT_FOUND)]
    public function history(
        #[CurrentUser] User $user,
        Bookmark $bookmark,
    ): JsonResponse {
        // If you call history on an outdated version it will also work by design
        $bookmarks = $this->bookmarkRepository->findOutdatedByOwnerAndUrl($user, $bookmark->url)
            ->getQuery()->getResult()
        ;

        return $this->jsonResponseBuilder->collection($bookmarks, ['bookmark:owner', 'tag:owner']);
    }

    #[Route(path: '/{id}', name: RouteAction::Patch->value, methods: ['PATCH'])]
    #[IsGranted(attribute: BookmarkVoter::OWNER, subject: 'bookmark', statusCode: Response::HTTP_NOT_FOUND)]
    public function patch(
        Bookmark $bookmark,
        #[MapRequestPayload(
            serializationContext: ['groups' => ['bookmark:owner']],
            validationGroups: ['Default'],
        )]
        Bookmark $bookmarkPayload,
    ): JsonResponse {
        // Manual merge
        $bookmark->title = $bookmarkPayload->title ?? $bookmark->title;
        // Url can not be updated by design
        $bookmark->mainImage = $bookmarkPayload->mainImage ?? $bookmark->mainImage;
        $bookmark->isPublic = $bookmarkPayload->isPublic ?? $bookmark->isPublic;
        $bookmark->tags = $bookmarkPayload->tags ?? $bookmark->tags;
        $bookmark->archive = $bookmarkPayload->archive ?? $bookmark->archive;
        // PDF is calculated

        try {
            $this->entityManager->flush();
        } catch (ORMInvalidArgumentException|ORMException $e) {
            throw new UnprocessableEntityHttpException(previous: $e);
        }

        return $this->jsonResponseBuilder->single($bookmark, ['bookmark:owner']);
    }

    #[Route(path: '/{id}', name: RouteAction::Delete->value, methods: ['DELETE'])]
    #[IsGranted(attribute: BookmarkVoter::OWNER, subject: 'bookmark', statusCode: Response::HTTP_NOT_FOUND)]
    public function delete(
        Bookmark $bookmark,
    ): JsonResponse {
        $this->entityManager->remove($bookmark);
        $this->entityManager->flush();

        return new JsonResponse(status: Response::HTTP_NO_CONTENT);
    }
}
