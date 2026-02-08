<?php

/** @noinspection HtmlUnknownTarget */

namespace App\ActivityPub\Bundler;

use App\ActivityPub\AccountFetch;
use App\ActivityPub\Dto\DocumentObject;
use App\ActivityPub\Dto\HashtagObject;
use App\ActivityPub\Dto\NoteObject;
use App\Config\RouteAction;
use App\Config\RouteType;
use App\Entity\Bookmark;
use App\Entity\FileObject;
use App\Entity\Follower;
use App\Service\InstanceTagService;
use App\Service\UrlGenerator;
use Doctrine\ORM\EntityManagerInterface;

final readonly class NoteObjectBundler
{
    public function __construct(
        private UrlGenerator $urlGenerator,
        private InstanceTagService $instanceTagService,
        private AccountFetch $accountFetch,
        private EntityManagerInterface $entityManager,
        private string $storageDefaultPublicPath,
        private string $baseUri,
    ) {
    }

    /**
     * @param array<int, Follower|string> $followers
     */
    public function bundleFromBookmark(Bookmark $bookmark, array $followers): NoteObject
    {
        $actorAccount = $bookmark->account;
        $url = $bookmark->url;

        $scheme = parse_url($url, \PHP_URL_SCHEME) . '://';
        $urlVisible = preg_replace("`^{$scheme}`", '', $url);
        $id = $this->urlGenerator->generate(
            RouteType::Profile,
            RouteAction::Get,
            ['username' => $actorAccount->username, 'id' => $bookmark->id],
        );
        $content = \sprintf('<p>%s <a href="%s" target="_blank" rel="nofollow noopener noreferrer"><span class="invisible">%s</span><span class="">%s</span></a> ', $bookmark->title, $url, $scheme, $urlVisible);

        $noteObject = new NoteObject();
        $noteObject->id = $id;
        $noteObject->url = $id;
        $noteObject->published = $bookmark->createdAt->format(\DATE_ATOM);
        $noteObject->attributedTo = $actorAccount->uri;

        $cc = [];
        foreach ($followers as $follower) {
            if ($follower instanceof Follower) {
                $cc[] = $follower->account->uri;
            } else {
                $cc[] = $follower;
            }
        }
        $noteObject->cc = $cc;

        $noteObject->attachment = array_filter([
            $this->bundleFromBookmarkFileObject($bookmark->mainImage),
            $this->bundleFromBookmarkFileObject($bookmark->archive),
        ]);

        $noteObject->tag = $this->bundleFromBookmarkTags($bookmark, $content);

        $noteObject->content = "{$content}</p>";

        return $noteObject;
    }

    public function unbundleToBookmark(NoteObject $noteObject): Bookmark
    {
        $actorAccount = $this->accountFetch->fetchFromUri($noteObject->attributedTo);
        $bookmark = new Bookmark();
        $bookmark->isPublic = true;
        $bookmark->account = $actorAccount;
        $bookmark->instance = $actorAccount->instance;

        // https://regex101.com/r/7xUY7D/3 (1,2, ...)
        $hrefRegex = '`href="([^"]+)"`miu';
        $titleRegex = '`(.*?)<a `misu';

        $html = $noteObject->content;

        $urls = [];
        preg_match($hrefRegex, $html, $urls);
        $bookmark->url = $urls[1]
            ?? throw new \LogicException('Did not find an url in Note Object.');

        $titles = [];
        preg_match($titleRegex, $html, $titles);
        $title = $titles[1]
            ?? throw new \LogicException('Did not find a title in Note Object.');
        $title = mb_trim(strip_tags($title));
        if (0 === mb_strlen($title)) {
            throw new \LogicException('Did not find a title in Note Object.');
        }
        $bookmark->title = $title;

        foreach ($noteObject->tag as $tag) {
            $bookmark->instanceTags->add($this->instanceTagService->findOrCreate(mb_ltrim($tag->name, '#')));
        }

        $hasMain = false;
        $hasArchive = false;
        foreach ($noteObject->attachment as $object) {
            if (str_starts_with($object->mediaType, 'image/') && !$hasMain) {
                $hasMain = true;
                $fileObject = new FileObject();
                $fileObject->size = -1;
                $fileObject->mime = $object->mediaType;
                $fileObject->filePath = $object->url;

                $this->entityManager->persist($fileObject);
                $bookmark->mainImage = $fileObject;
            }

            if (\in_array($object->mediaType, ['application/gzip', 'text/html']) && !$hasArchive) {
                $hasArchive = true;
                $fileObject = new FileObject();
                $fileObject->size = -1;
                $fileObject->mime = $object->mediaType;
                $fileObject->filePath = $object->url;

                $this->entityManager->persist($fileObject);
                $bookmark->archive = $fileObject;
            }
        }

        return $bookmark;
    }

    private function bundleFromBookmarkFileObject(?FileObject $fileObject): ?DocumentObject
    {
        if (!$fileObject) {
            return null;
        }

        $documentObject = new DocumentObject();
        $documentObject->url =
            // TODO use flysystem instead
            $this->baseUri . $this->storageDefaultPublicPath . '/' . $fileObject->filePath;
        $documentObject->mediaType = $fileObject->mime;
        $documentObject->name = 'Bookmark Archive';

        return $documentObject;
    }

    /**
     * @param string $content update content with tags if needed
     *
     * @return array<int, HashtagObject>
     */
    private function bundleFromBookmarkTags(Bookmark $bookmark, string &$content): array
    {
        $hashtags = [];
        foreach ($bookmark->userTags as $tag) {
            if (!$tag->isPublic) {
                continue;
            }
            $tagUrl = $this->urlGenerator->generate(
                RouteType::ProfileBookmarks,
                RouteAction::Collection,
                ['username' => $bookmark->account->username, 'tags' => $tag->slug],
            );
            $hashtag = new HashtagObject();
            $hashtag->href = $tagUrl;
            $hashtag->name = '#' . $tag->name;
            $hashtags[] = $hashtag;
            $content .= \sprintf('<a href="%s" target="_blank" rel="nofollow noopener noreferrer tag" class="mention hashtag">#<span>%s</span></a>', $tagUrl, $tag->name);
        }

        return $hashtags;
    }
}
