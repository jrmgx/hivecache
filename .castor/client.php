<?php

namespace client;

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\io;
use function Castor\run;

#[AsTask(description: 'Install dependencies')]
function install(): void
{
    io()->title('Install dependencies');

    run('yarn install', context: context()->withWorkingDirectory('./client'));
}

#[AsTask(description: 'Start the dev server in watch mode', aliases: ['client:start'])]
function watch(): void
{
    io()->title('Start the dev server in watch mode');

    run('yarn run vite', context: context()->withWorkingDirectory('./client'));
}

#[AsTask(description: 'Build the production artifact')]
function build(#[AsArgument] $toDirectory = '../client'): void
{
    io()->title('Build the production artifact');

    $styleGuideFile = './client/src/pages/Styleguide.tsx';

    rename($styleGuideFile, $styleGuideFile.'_skip');
    file_put_contents($styleGuideFile, 'export const Styleguide = () => null;');
    run('yarn run tsc -b && yarn run vite build', context: context()->withWorkingDirectory('./client'));
    rename($styleGuideFile.'_skip', $styleGuideFile);

    $toDirectory = rtrim($toDirectory, '/');
    run("rm -rfv $toDirectory/*");
    run("cp -rfv ./client/dist/* $toDirectory/");
}

#[AsTask(description: 'Lint code')]
function lint(): void
{
    io()->title('Lint code');

    run('yarn run eslint .', context: context()->withWorkingDirectory('./client'));
}
