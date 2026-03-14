<?php

/** @noinspection PhpRedundantCatchClauseInspection */

namespace App\Api\Controller;

use App\Api\Config\RouteAction;
use App\Api\Config\RouteType;
use App\Api\Dto\NoteApiDto;
use App\Api\Response\JsonResponseBuilder;
use App\Api\Security\Voter\NoteVoter;
use App\Api\UrlGenerator;
use App\Entity\Note;
use App\Entity\User;
use App\Repository\BookmarkRepository;
use App\Repository\NoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\ORMInvalidArgumentException;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/users/me/notes', name: RouteType::MeNotes->value)]
final class MeNoteController extends AbstractController
{
    public function __construct(
        protected readonly NoteRepository $noteRepository,
        protected readonly BookmarkRepository $bookmarkRepository,
        protected readonly EntityManagerInterface $entityManager,
        protected readonly JsonResponseBuilder $jsonResponseBuilder,
        protected readonly UrlGenerator $urlGenerator,
    ) {
    }

    #[OA\Post(
        path: '/users/me/notes',
        tags: ['Notes'],
        operationId: 'createNote',
        summary: 'Create a new note',
        description: 'Creates a new note associated with a bookmark. The bookmark must be owned by the authenticated user.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Note data',
            content: new OA\JsonContent(ref: '#/components/schemas/NoteCreate')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Note created successfully',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/NoteShowPrivate',
                    examples: [
                        new OA\Examples(
                            example: 'created_note',
                            value: [
                                'id' => Note::EXAMPLE_NOTE_ID,
                                'createdAt' => '2024-01-01T12:00:00+00:00',
                                'content' => 'My note content',
                                '@iri' => Note::EXAMPLE_NOTE_IRI,
                            ],
                            summary: 'Successfully created note'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - authentication required'
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error - invalid data',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ErrorResponse',
                    examples: [
                        new OA\Examples(
                            example: 'invalid_bookmark',
                            value: ['error' => ['code' => 422, 'message' => 'Invalid bookmark IRI']],
                            summary: 'Invalid bookmark reference'
                        ),
                    ]
                )
            ),
        ]
    )]
    #[Route(path: '', name: RouteAction::Create->value, methods: ['POST'])]
    public function create(
        #[CurrentUser] User $user,
        #[MapRequestPayload(
            serializationContext: ['groups' => ['note:create']],
            validationGroups: ['Default', 'note:create'],
        )]
        NoteApiDto $notePayload,
    ): JsonResponse {
        $bookmark = $notePayload->bookmark ?? throw new BadRequestHttpException();
        if ($bookmark->account->owner !== $user) {
            throw new UnprocessableEntityHttpException('Bookmark not owned by user');
        }

        $existingNote = $this->noteRepository->findOneByBookmarkAndUser($bookmark, $user);
        if ($existingNote) {
            throw new UnprocessableEntityHttpException('Bookmark already has a note');
        }

        $note = new Note();
        $note->content = $notePayload->content ?? throw new BadRequestHttpException();
        $note->owner = $user;
        $note->bookmark = $bookmark;

        try {
            $this->entityManager->persist($note);
            $this->entityManager->flush();
        } catch (ORMInvalidArgumentException|ORMException $e) {
            throw new UnprocessableEntityHttpException(previous: $e);
        }

        return $this->jsonResponseBuilder->single($note, ['note:show:private']);
    }

    #[OA\Get(
        path: '/users/me/notes/{id}',
        tags: ['Notes'],
        operationId: 'getOwnNote',
        summary: 'Get own note by ID',
        description: 'Returns a specific note owned by the authenticated user.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\PathParameter(
                name: 'id',
                description: 'Note ID',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Note details',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/NoteShowPrivate'
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - authentication required'
            ),
            new OA\Response(
                response: 404,
                description: 'Note not found or not owned by user'
            ),
        ]
    )]
    #[Route(path: '/{id}', name: RouteAction::Get->value, methods: ['GET'])]
    #[IsGranted(attribute: NoteVoter::OWNER, subject: 'note', statusCode: Response::HTTP_NOT_FOUND)]
    public function get(
        Note $note,
    ): JsonResponse {
        return $this->jsonResponseBuilder->single($note, ['note:show:private']);
    }

    #[OA\Patch(
        path: '/users/me/notes/{id}',
        tags: ['Notes'],
        operationId: 'updateNote',
        summary: 'Update a note',
        description: 'Updates an existing note. Only content can be modified.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\PathParameter(
                name: 'id',
                description: 'Note ID',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            description: 'Note update data',
            content: new OA\JsonContent(ref: '#/components/schemas/NoteUpdate')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Note updated successfully',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/NoteShowPrivate'
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - authentication required'
            ),
            new OA\Response(
                response: 404,
                description: 'Note not found'
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error - invalid data',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ErrorResponse',
                    examples: [
                        new OA\Examples(
                            example: 'invalid_data',
                            value: ['error' => ['code' => 422, 'message' => 'Unprocessable Content']],
                            summary: 'Validation error'
                        ),
                    ]
                )
            ),
        ]
    )]
    #[Route(path: '/{id}', name: RouteAction::Patch->value, methods: ['PATCH'])]
    #[IsGranted(attribute: NoteVoter::OWNER, subject: 'note', statusCode: Response::HTTP_NOT_FOUND)]
    public function patch(
        Note $note,
        #[MapRequestPayload(
            serializationContext: ['groups' => ['note:update']],
            validationGroups: ['Default', 'note:update'],
        )]
        NoteApiDto $notePayload,
    ): JsonResponse {
        if (isset($notePayload->content)) {
            $note->content = $notePayload->content;
        }

        try {
            $this->entityManager->flush();
        } catch (ORMInvalidArgumentException|ORMException $e) {
            throw new UnprocessableEntityHttpException(previous: $e);
        }

        return $this->jsonResponseBuilder->single($note, ['note:show:private']);
    }

    #[OA\Delete(
        path: '/users/me/notes/{id}',
        tags: ['Notes'],
        operationId: 'deleteNote',
        summary: 'Delete a note',
        description: 'Permanently deletes a note owned by the authenticated user.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\PathParameter(
                name: 'id',
                description: 'Note ID',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Note deleted successfully'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - authentication required'
            ),
            new OA\Response(
                response: 404,
                description: 'Note not found or not owned by user'
            ),
        ]
    )]
    #[Route(path: '/{id}', name: RouteAction::Delete->value, methods: ['DELETE'])]
    #[IsGranted(attribute: NoteVoter::OWNER, subject: 'note', statusCode: Response::HTTP_NOT_FOUND)]
    public function delete(
        Note $note,
    ): JsonResponse {
        $this->entityManager->remove($note);
        $this->entityManager->flush();

        return new JsonResponse(status: Response::HTTP_NO_CONTENT);
    }
}
