<?php

namespace extension;

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\fs;
use function Castor\io;
use function Castor\run;

#[AsTask(description: 'Install dependencies')]
function install(): void
{
    io()->title('Install dependencies');

    run('yarn install', context: context()->withWorkingDirectory('./extension'));
}

#[AsTask(description: 'Build the production artifact')]
function build(): void
{
    io()->title('Build the production artifact');

    $extensionDir = './extension';
    $context = context()->withWorkingDirectory($extensionDir);

    run('yarn build', context: $context);

    io()->section('Creating web extension zip file');

    $zipPath = $extensionDir . '/hivecache-extension.zip';
    $zip = new \ZipArchive();

    if (true !== $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
        throw new \RuntimeException("Cannot create zip file: {$zipPath}");
    }

    try {
        fs()->copy($extensionDir . '/manifest-prod.json', $extensionDir . '/manifest.json', overwriteNewerFiles: true);

        $files = [
            'manifest.json',
            'popup.html',
            'options.html',
            'background.js',
            'content.js',
            'popup.js',
            'options.js',
            'icons/icon.svg',
            'lib/tom-select.complete.min.js',
            'lib/tom-select.css',
        ];

        foreach ($files as $file) {
            $filePath = $extensionDir . '/' . $file;
            if (!file_exists($filePath)) {
                io()->error("File not found: {$file}");

                return;
            }
            $zip->addFile($filePath, $file);
            io()->writeln("Added: {$file}");
        }

        $zip->close();
    } catch (\Exception $e) {
        io()->error($e->getMessage());
    } finally {
        fs()->copy($extensionDir . '/manifest-dev.json', $extensionDir . '/manifest.json', overwriteNewerFiles: true);
    }

    io()->success("Web extension zip created: {$zipPath}");
}

#[AsTask(description: 'Start the dev server in watch mode', aliases: ['extension:start'])]
function watch(): void
{
    io()->title('Started the dev server in watch mode');

    run('yarn watch', context: context()->withWorkingDirectory('./extension'));
}

#[AsTask(description: 'Type check TypeScript files')]
function typecheck(): void
{
    io()->title('Type check TypeScript files');

    run('yarn typecheck', context: context()->withWorkingDirectory('./extension'));
}

#[AsTask(description: 'Clean build artifacts')]
function clean(): void
{
    io()->title('Clean build artifacts');

    run('yarn clean', context: context()->withWorkingDirectory('./extension'));
}

#[AsTask(description: 'Lint code')]
function lint(): void
{
    io()->title('Lint code');

    io()->warning('ESLint is not configured for the extension. Skipping lint task.');
    // TODO: Add eslint configuration to extension
    // run('yarn run eslint .', context: context()->withWorkingDirectory('./extension'));
}
