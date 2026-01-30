<?php

/*
 * The importer is a client that will try to import urls from the command line.
 * It will do it via an headless browser + "extention" so you realy get the full archive.
 *
 * TODO add more documentation
 * Including what is needed on the host machine, basically yarn, docker and php
 */

namespace importer;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsPathArgument;
use Castor\Attribute\AsTask;
use Shaarli\NetscapeBookmarkParser\NetscapeBookmarkParser;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function Castor\context;
use function Castor\fs;
use function Castor\http_client;
use function Castor\io;
use function Castor\run;
use function extension\build;
use function extension\install;

// You may want to change that in production to true
const SECURITY_STRICT = false;
const EXTENTION_CONTENT_FILE = './extension/content.js';
const CONFIG_FILE = '.importer.json';

#[AsTask(description: 'You need to start this service to get the importer working')]
function start(): void
{
    run('docker run --rm -d -p 8090:3000 --name browserless -e TOKEN=browserless-token ghcr.io/browserless/chromium:latest', context()->withAllowFailure(true));
}

#[AsTask(description: 'Capture a specific URL from the command line')]
function url(
    string $url,
    #[AsOption(description: 'You can specify tags as a separated by comma string like: example,capture')]
    ?string $tags = null,
): int {
    ensureServiceRunning();
    firstAuth();
    buildExtentionCode();

    io()->writeln("⏳ Procecing url: {$url}");

    [$title, $mainImageIri, $archiveIri] = capture($url);

    $bookmarkTags = [];
    foreach ($tags ? explode(',', $tags) : [] as $tagName) {
        $bookmarkTags[] = createTag($tagName);
    }

    createBookmark(
        title: $title,
        url: $url,
        tags: $bookmarkTags,
        archive: $archiveIri,
        mainImage: $mainImageIri,
    );

    io()->writeln('✅ Success');

    return 0;
}

#[AsTask(description: 'Export your bookmarks from any service and import it with this command')]
function file(
    #[AsPathArgument]
    string $file,
    #[AsOption(description: 'You can specify an "id" and the importer will continue after this one')]
    ?string $continueAfter = null,
    #[AsOption(description: 'Save failed captures. When something wrong happen, still create an entry')]
    bool $saveFailedCaptures = false,
): int {
    ensureServiceRunning();
    firstAuth();
    buildExtentionCode();

    ini_set('max_execution_time', -1);
    ini_set('memory_limit', '1G');

    io()->info('Parsing file...');
    /** @var array<string, string> $tagCache */
    $tagCache = [];
    $parser = new NetscapeBookmarkParser();

    /**
     * @var list<array{
     *     name: string,
     *     image: string|null,
     *     url: string,
     *     tags: array<string>,
     *     description: string|null,
     *     dateCreated: int,
     *     public: bool
     * }> $bookmarks
     */
    $bookmarks = $parser->parseFile($file);
    // Sort by dateCreated (oldest first)
    usort($bookmarks, fn ($a, $b) => ($a['dateCreated'] ?? 0) <=> ($b['dateCreated'] ?? 0));
    $bookmarks = array_combine(array_map(fn ($b) => sha1($b['url']), $bookmarks), $bookmarks);

    $current = 0;
    $total = \count($bookmarks);

    $needSkip = null !== $continueAfter;
    foreach ($bookmarks as $hash => $bookmark) {
        ++$current;
        $currentTotal = "{$current}/{$total}";
        if ($needSkip) {
            io()->writeln("[{$currentTotal} id:{$hash}] ⏩️ Skipped");
            if ($hash === $continueAfter) {
                $needSkip = false;

                continue;
            }

            continue;
        }

        $url = $bookmark['url'];
        io()->writeln("[{$currentTotal} id:{$hash}] ⏳ Procecing url: " . $bookmark['url']);

        try {
            $bookmarkTags = [];
            foreach ($bookmark['tags'] ?? [] as $tagName) {
                if (!isset($tagCache[$tagName])) {
                    $tagCache[$tagName] = createTag($tagName);
                }
                $bookmarkTags[] = $tagCache[$tagName];
            }
            [$title, $mainImageIri, $archiveIri] = capture($url);
            createBookmark(
                title: $bookmark['name'] ?? $title,
                url: $bookmark['url'],
                tags: $bookmarkTags,
                mainImage: $mainImageIri,
                archive: $archiveIri,
            );
            io()->writeln("[{$currentTotal} id:{$hash}] ✅ Success");
        } catch (\Exception $e) {
            if ($saveFailedCaptures) {
                try {
                    createBookmark(
                        title: $bookmark['name'],
                        url: $bookmark['url'],
                        tags: $bookmarkTags,
                    );
                    io()->writeln("[{$currentTotal} id:{$hash}] ⚠️ Something went wrong, but the entry has been created");
                } catch (\Exception $e) {
                    io()->writeln("[{$currentTotal} id:{$hash}] ❌ Error: " . $e->getMessage());
                }
            } else {
                io()->writeln("[{$currentTotal} id:{$hash}] ❌ Error: " . $e->getMessage());
            }
        }
    }

    return 0;
}

#[AsTask(description: 'Re-capture all your existing bookmarks to get a new version in their history')]
function reCapture(
    #[AsOption(description: 'You can specify an "id" and the importer will continue after this one')]
    ?string $continueAfter = null,
): int {
    ensureServiceRunning();
    firstAuth();
    buildExtentionCode();

    ini_set('max_execution_time', -1);
    ini_set('memory_limit', '1G');

    io()->info('Loading bookmarks...');
    /** @var array<string, array> $bookmarks */
    $bookmarks = fetchAllBookmarks();
    ksort($bookmarks);

    $current = 0;
    $total = \count($bookmarks);

    $needSkip = null !== $continueAfter;
    foreach ($bookmarks as $hash => $bookmark) {
        ++$current;
        $currentTotal = "{$current}/{$total}";
        if ($needSkip) {
            io()->writeln("[{$currentTotal} id:{$hash}] ⏩️ Skipped");
            if (strcasecmp($hash, $continueAfter) >= 0) {
                $needSkip = false;

                continue;
            }

            continue;
        }

        $url = $bookmark['url'];
        io()->writeln("[{$currentTotal} id:{$hash}] ⏳ Procecing url: {$url}");

        try {
            [, $mainImageIri, $archiveIri] = capture($url);

            createBookmark(
                title: $bookmark['title'],
                url: $url,
                isPublic: $bookmark['isPublic'],
                mainImage: $bookmark['mainImage']['@iri'] ?? $mainImageIri,
                archive: $archiveIri,
            );

            io()->writeln("[{$currentTotal} id:{$hash}] ✅ Success");
        } catch (\Exception $e) {
            io()->writeln("[{$currentTotal} id:{$hash}] ❌ Error: " . $e->getMessage());
        }
    }

    return 0;
}

#[AsTask(description: 'Logout')]
function logout(): void
{
    if (fs()->exists(CONFIG_FILE)) {
        fs()->remove(CONFIG_FILE);
    }
    io()->success('Logged out');
}

#[AsTask(description: 'Stop the importer needed service')]
function stop(): void
{
    run('docker stop browserless 2>/dev/null || true', context()->withAllowFailure(true));
}

/**
 * @return array{0: string, 1: ?string, 2: string} Title, Main Image IRI, Archive IRI
 */
function capture(string $url): array
{
    $result = fetchHtmlFromUrl($url);
    $html = $result['html'];
    $metadata = $result['metadata'];
    unset($result);

    // archive file
    $compressedHtml = compressHTML($html);
    $tempFile = sys_get_temp_dir() . '/' . uniqid('hivecache_archive_') . '.gz';
    fs()->dumpFile($tempFile, $compressedHtml);
    unset($compressedHtml);
    $archiveFileObject = uploadFileObject($tempFile);
    $archiveIri = $archiveFileObject['@iri'];
    fs()->remove($tempFile);

    // mainImage file
    $mainImageIri = null;
    if ($imageUrl = $metadata['image'] ?? null) {
        try {
            $imageResponse = http_client()->request('GET', $imageUrl, ['timeout' => 30]);
            if (200 === $imageResponse->getStatusCode()) {
                $imageContent = $imageResponse->getContent();
                $contentType = $imageResponse->getHeaders()['content-type'][0] ?? '';
                $extension = 'jpg'; // default
                if (preg_match('/image\/(jpe?g|png|gif|webp|svg)/i', $contentType, $matches)) {
                    $extension = $matches[1];
                } else {
                    $urlPath = parse_url($imageUrl, \PHP_URL_PATH);
                    if ($urlPath && preg_match('/\.(jpe?g|png|gif|webp|svg)$/i', $urlPath, $matches)) {
                        $extension = $matches[1];
                    }
                }
                $extension = str_replace('jpeg', 'jpg', $extension);
                $imageTempFile = sys_get_temp_dir() . '/' . uniqid('hivecache_image_') . '.' . $extension;
                fs()->dumpFile($imageTempFile, $imageContent);
                unset($imageContent);
                $imageFileObject = uploadFileObject($imageTempFile);
                $mainImageIri = $imageFileObject['@iri'];
                fs()->remove($imageTempFile);
            }
            unset($imageResponse);
        } catch (\Exception $e) {
            io()->warning("Failed to download/upload image from '{$imageUrl}': " . $e->getMessage());
        }
    }

    return [$metadata['title'], $mainImageIri, $archiveIri];
}

/**
 * Build extension if content.js is missing (needed for page capture functionality).
 */
function buildExtentionCode(): void
{
    if (!fs()->exists(EXTENTION_CONTENT_FILE)) {
        io()->section('Building extension');
        io()->note('Extension content.js not found. Building extension code required for capturing pages...');
        install();
        build();
    }
}

function ensureServiceRunning(): void
{
    $result = run(
        'docker ps --filter name=browserless --format "{{.Names}}"',
        context()->withAllowFailure(true)->withQuiet(true)
    );
    $runing = 'browserless' === mb_trim($result->getOutput());
    if (!$runing) {
        throw new \Exception("Please start the importer service first.\n$ castor importer:start");
    }
}

/**
 * Fetches HTML content from a URL using Browserless headless browser
 * Injects extension/content.js and runs the archive page steps.
 *
 * @return array{
 *     html: string,
 *     metadata: ?array{
 *         title: string,
 *         url: string,
 *         description: ?string,
 *         image: ?string,
 *     }
 * } HTML content and page metadata after archive processing
 */
function fetchHtmlFromUrl(string $url): array
{
    // Modify the script to expose functions to window
    $contentJs = str_replace(
        "})();\n", "\nwindow.__archiveFunctions = { removeAllScripts, removeNoscriptAndIframes, inlineAllCSS, embedAllImages, disableAllLinks, cleanupHead, extractPageMetadata };})();\n",
        file_get_contents(EXTENTION_CONTENT_FILE)
    );

    $urlJson = json_encode($url, \JSON_UNESCAPED_SLASHES);
    $contentJsJson = json_encode($contentJs, \JSON_UNESCAPED_SLASHES);

    // Build the Puppeteer function code
    $functionCode = <<<JS
        // noinspection JSUnresolvedReference

        export default async function ({ page }) {
            await page.goto({$urlJson}, { waitUntil: 'networkidle2', timeout: 60000 });
            // noinspection JSAnnotator
            const contentJs = {$contentJsJson};
            // Execute archive steps and get metadata
            const pageMetadata = await page.evaluate(async (script) => {
                eval(script);
                let metadata = null;
                if (window.__archiveFunctions) {
                    metadata = await window.__archiveFunctions.extractPageMetadata();
                    window.__archiveFunctions.removeAllScripts();
                    window.__archiveFunctions.removeNoscriptAndIframes();
                    await window.__archiveFunctions.inlineAllCSS();
                    await window.__archiveFunctions.embedAllImages();
                    window.__archiveFunctions.disableAllLinks();
                    window.__archiveFunctions.cleanupHead();
                } else {
                    throw new Error('Archive functions not available. Script may not have loaded correctly.');
                }
                return metadata;
            }, contentJs);
            const html = await page.content();
            return {
                data: JSON.stringify({ html: html, metadata: pageMetadata}),
                type: 'application/json',
            };
        }
        JS;

    $response = http_client()->request('POST', 'http://localhost:8090/function?token=browserless-token', [
        'headers' => ['Content-Type' => 'application/json'],
        'json' => ['code' => $functionCode],
        'timeout' => 120, // Allow more time for archive processing (image embedding, etc.)
    ]);
    unset($contentJs, $contentJsJson, $urlJson, $functionCode);

    $statusCode = $response->getStatusCode();
    if (200 !== $statusCode) {
        $errorContent = $response->getContent(false);
        unset($response);

        throw new \RuntimeException("Browserless failed to fetch HTML from '{$url}': HTTP {$statusCode} - {$errorContent}");
    }

    $result = $response->toArray();
    unset($response);
    if (!isset($result['data'])) {
        throw new \RuntimeException('Error data key not found.');
    }

    $parsedData = json_decode($result['data'], true);
    if (\JSON_ERROR_NONE !== json_last_error() || !\is_array($parsedData)) {
        unset($parsedData);

        throw new \RuntimeException('Error invalid JSON response.');
    }

    return $parsedData;
}

/**
 * Fetch all bookmarks from the API using pagination.
 *
 * @return array<string, array> Array indexed by bookmark ID
 */
function fetchAllBookmarks(): array
{
    $allBookmarks = [];
    $after = null;

    do {
        $endpoint = '/users/me/bookmarks/search/index';
        if (null !== $after) {
            $endpoint .= '?after=' . urlencode($after);
        }

        $response = makeAuthenticatedRequest('GET', $endpoint);
        $statusCode = $response->getStatusCode();

        if (200 !== $statusCode) {
            $errorContent = $response->getContent(false);

            throw new \RuntimeException("Failed to fetch bookmarks: HTTP {$statusCode} - {$errorContent}");
        }

        $data = $response->toArray();
        unset($response);

        if (!isset($data['collection']) || !\is_array($data['collection'])) {
            throw new \RuntimeException('Invalid response format: collection not found');
        }

        // Index bookmarks by their ID
        foreach ($data['collection'] as $bookmark) {
            $allBookmarks[sha1($bookmark['url'])] = $bookmark;
        }

        // Check if there's a next page
        $nextPage = $data['nextPage'] ?? null;
        if (null !== $nextPage) {
            // Extract the 'after' parameter from the nextPage URL
            $parsedUrl = parse_url($nextPage);
            if (isset($parsedUrl['query'])) {
                parse_str($parsedUrl['query'], $queryParams);
                $after = $queryParams['after'] ?? null;
            } else {
                // If no query params, use the last bookmark's ID
                $lastBookmark = end($data['collection']);
                $after = $lastBookmark['id'] ?? null;
            }
        } else {
            $after = null;
        }
    } while (null !== $after);

    return $allBookmarks;
}

/**
 * @return array{instance: string, username: string, token: string}
 */
function loadCredentials(): array
{
    $configContent = file_get_contents(CONFIG_FILE);
    if (false === $configContent) {
        throw new \RuntimeException('Failed to read configuration file.');
    }

    $config = json_decode($configContent, true);
    if (!isset($config['credential'])) {
        throw new \RuntimeException('Invalid configuration file format.');
    }
    unset($configContent);

    $credential = $config['credential'];
    if (!isset($credential['instance'], $credential['token'])) {
        throw new \RuntimeException('Missing required credentials in configuration file.');
    }

    return [
        'instance' => mb_rtrim($credential['instance'], '/'),
        'username' => $credential['username'] ?? '',
        'token' => $credential['token'],
    ];
}

function getHttpClient(): HttpClientInterface
{
    return http_client()->withOptions([
        'verify_peer' => SECURITY_STRICT,
        'verify_host' => SECURITY_STRICT,
    ]);
}

/**
 * @param array<string, mixed> $options Request options
 */
function makeAuthenticatedRequest(string $method, string $endpoint, array $options = []): ResponseInterface
{
    $credentials = loadCredentials();
    $baseUrl = $credentials['instance'];
    $url = $baseUrl . $endpoint;
    $defaultHeaders = [
        'Authorization' => 'Bearer ' . $credentials['token'],
        'Accept' => 'application/json',
    ];

    $headers = array_merge($defaultHeaders, $options['headers'] ?? []);
    $options['headers'] = $headers;

    return getHttpClient()->request($method, $url, $options);
}

/**
 * @return string Tag IRI
 */
function createTag(string $tagName): string
{
    $response = makeAuthenticatedRequest('POST', '/users/me/tags', [
        'headers' => ['Content-Type' => 'application/json'],
        'json' => ['name' => $tagName],
    ]);

    $statusCode = $response->getStatusCode();
    if (201 !== $statusCode && 200 !== $statusCode) {
        throw new \RuntimeException("Failed to create tag '{$tagName}': HTTP {$statusCode}");
    }

    $data = $response->toArray();
    unset($response);
    if (!isset($data['@iri'])) {
        throw new \RuntimeException("Tag created but IRI not found in response for '{$tagName}'");
    }

    return $data['@iri'];
}

function createBookmark(
    string $title,
    string $url,
    bool $isPublic = false,
    ?array $tags = null,
    ?string $mainImage = null,
    ?string $archive = null,
): void {
    $payload = [
        'title' => $title,
        'url' => $url,
        'isPublic' => $isPublic,
    ];

    if (null !== $tags) {
        $payload['tags'] = $tags;
    }

    if (null !== $mainImage) {
        $payload['mainImage'] = $mainImage;
    }

    if (null !== $archive) {
        $payload['archive'] = $archive;
    }

    $response = makeAuthenticatedRequest('POST', '/users/me/bookmarks', [
        'headers' => ['Content-Type' => 'application/json'],
        'json' => $payload,
    ]);

    $statusCode = $response->getStatusCode();
    if (201 !== $statusCode && 200 !== $statusCode) {
        $errorContent = $response->getContent(false);
        unset($response);

        throw new \RuntimeException("Failed to create bookmark '{$title}': HTTP {$statusCode} - {$errorContent}");
    }

    unset($response);
}

/**
 * @return string The compressed HTML as binary string (gzip format)
 */
function compressHTML(string $html): string
{
    $compressed = gzencode($html, 6);
    unset($html);
    if (false === $compressed) {
        throw new \RuntimeException('Failed to compress HTML');
    }

    return $compressed;
}

/**
 * @return array{
 *     contentUrl: string|null,
 *     size: int,
 *     mime: string,
 *
 *     @iri: string
 * }
 */
function uploadFileObject(string $filePath): array
{
    if (!fs()->exists($filePath)) {
        throw new \RuntimeException("File not found: {$filePath}");
    }

    $filename = basename($filePath);

    $mimeType = 'application/octet-stream';
    if (str_ends_with(mb_strtolower($filename), '.gz')) {
        $mimeType = 'application/gzip';
    }

    $fileHandle = fopen($filePath, 'r');
    if (false === $fileHandle) {
        throw new \RuntimeException("Failed to open file: {$filePath}");
    }

    try {
        $boundary = uniqid('----WebKitFormBoundary', true);
        $bodyGenerator = function () use ($boundary, $filename, $mimeType, $fileHandle): \Generator {
            yield "--{$boundary}\r\n";
            yield "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n";
            yield "Content-Type: {$mimeType}\r\n\r\n";

            while (!feof($fileHandle)) {
                $chunk = fread($fileHandle, 8192); // 8KB chunks
                if (false !== $chunk) {
                    yield $chunk;
                }
            }

            yield "\r\n--{$boundary}--\r\n";
        };

        $response = makeAuthenticatedRequest('POST', '/users/me/files', [
            'headers' => [
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ],
            'body' => $bodyGenerator(),
        ]);

        $statusCode = $response->getStatusCode();
        if (200 !== $statusCode) {
            $errorContent = $response->getContent(false);

            throw new \RuntimeException("Failed to upload file '{$filename}': HTTP {$statusCode} - {$errorContent}");
        }

        return $response->toArray();
    } finally {
        fclose($fileHandle);
    }
}

function firstAuth(): void
{
    if (!fs()->exists(CONFIG_FILE)) {
        io()->title('Configuration Required');
        run('castor composer install');

        io()->note('No configuration file found. Please provide your credentials.');

        // Ask for credentials
        $instance = io()->ask('Instance URL', 'https://hivecache.test', function ($value) {
            if (empty($value)) {
                throw new \RuntimeException('Instance URL cannot be empty.');
            }
            if (!preg_match('/^https?:\/\//', $value)) {
                $value = 'https://' . $value;
            }

            return mb_rtrim($value, '/');
        });

        $username = io()->ask('Username', validator: function ($value) {
            if (empty($value)) {
                throw new \RuntimeException('Username cannot be empty.');
            }

            return $value;
        });

        $password = io()->askHidden('Password');

        io()->section('Authenticating...');

        $httpClient = getHttpClient();
        $authUrl = $instance . '/auth';

        try {
            $response = $httpClient->request('POST', $authUrl, [
                'headers' => ['Content-Type' => 'application/json',  'Accept' => 'application/json'],
                'json' => ['username' => $username,  'password' => $password],
            ]);

            $statusCode = $response->getStatusCode();

            if (200 !== $statusCode) {
                $errorContent = $response->getContent(false);

                throw new \RuntimeException("Authentication failed with status {$statusCode}: {$errorContent}");
            }

            $data = $response->toArray();

            if (!isset($data['token'])) {
                throw new \RuntimeException('Token not found in authentication response.');
            }

            $token = $data['token'];

            fs()->dumpFile(CONFIG_FILE, json_encode([
                'credential' => [
                    'instance' => $instance,
                    'username' => $username,
                    'token' => $token,
                ],
            ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));

            io()->success('Authentication successful! Credentials saved to ' . CONFIG_FILE);
        } catch (\Exception $e) {
            throw new \RuntimeException('Authentication error: ' . $e->getMessage(), 0, $e);
        }
    } else {
        io()->info('Configuration file found: ' . CONFIG_FILE);
    }
}
