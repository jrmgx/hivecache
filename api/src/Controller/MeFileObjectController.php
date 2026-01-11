<?php

namespace App\Controller;

use App\Config\RouteAction;
use App\Config\RouteType;
use App\Entity\Bookmark;
use App\Entity\FileObject;
use App\Entity\User;
use App\Naming\HashAndSubdirectories;
use App\Response\JsonResponseBuilder;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route(path: '/users/me/files', name: RouteType::MeFileObjects->value)]
final class MeFileObjectController extends AbstractController
{
    public function __construct(
        private readonly HashAndSubdirectories $hashAndSubdirectories,
        #[Autowire('@default.storage')]
        private readonly FilesystemOperator $filesystemOperator,
        private readonly EntityManagerInterface $entityManager,
        private readonly JsonResponseBuilder $jsonResponseBuilder,
    ) {
    }

    /**
     * Placeholder route for iri generation.
     */
    #[OA\Get(
        path: '/users/me/files/{id}',
        tags: ['Files'],
        operationId: 'getFileObject',
        summary: 'Get file object (not supported)',
        description: 'This endpoint is not supported. Use POST to upload files.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\PathParameter(
                name: 'id',
                description: 'File object ID',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(
                response: 405,
                description: 'Method not allowed - use POST to upload files'
            ),
        ]
    )]
    #[Route(path: '/{id}', name: RouteAction::Get->value, methods: ['GET'])]
    public function get(): JsonResponse
    {
        throw new MethodNotAllowedHttpException(['POST']);
    }

    #[OA\Post(
        path: '/users/me/files',
        tags: ['Files'],
        operationId: 'uploadFile',
        summary: 'Upload a file',
        description: 'Uploads a file and returns a FileObject that can be referenced in bookmarks (mainImage, archive).',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'File to upload',
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['file'],
                    properties: [
                        new OA\Property(
                            property: 'file',
                            type: 'string',
                            format: 'binary',
                            description: 'File to upload'
                        ),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'File uploaded successfully',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/FileObject',
                    examples: [
                        new OA\Examples(
                            example: 'uploaded_file',
                            value: [
                                'contentUrl' => 'https://bookmarkhive.test/storage/files/abc123.jpg',
                                'size' => 102400,
                                'mime' => 'image/jpeg',
                                '@iri' => 'https://bookmarkhive.test/users/me/files/' . Bookmark::EXAMPLE_BOOKMARK_ID,
                            ],
                            summary: 'Successfully uploaded file'
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
                description: 'Validation error - invalid file',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ErrorResponse',
                    examples: [
                        new OA\Examples(
                            example: 'invalid_file',
                            value: ['error' => ['code' => 422, 'message' => 'Unprocessable Content']],
                            summary: 'Invalid file validation error'
                        ),
                    ]
                )
            ),
        ]
    )]
    #[Route(path: '', name: RouteAction::Create->value, methods: ['POST'])]
    public function create(
        #[CurrentUser] User $user,
        Request $request,
    ): JsonResponse {
        /** @var UploadedFile $file */
        $file = $request->files->get('file');

        $name = $this->hashAndSubdirectories->name();
        $ext = $file->guessExtension() ?? 'bin';
        $filePath = $name . '.' . $ext;

        $this->filesystemOperator->write($filePath, $file->getContent());

        $fileObject = new FileObject();
        $fileObject->owner = $user;
        $fileObject->size = (int) $file->getSize();
        $fileObject->mime = $file->getMimeType() ?? 'application/octet-stream';
        $fileObject->filePath = $filePath;

        $this->entityManager->persist($fileObject);
        $this->entityManager->flush();

        return $this->jsonResponseBuilder->single($fileObject, ['file_object:read']);
    }
}
