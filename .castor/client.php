<?php

namespace client;

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask(description: 'Start the dev server in watch mode')]
function start(): void
{
    run('yarn run vite', context: context()->withWorkingDirectory('./client'));
}

#[AsTask(description: 'Build the production artifact')]
function build(#[AsArgument] $defaultInstance, #[AsArgument] $toDirectory): void
{
    $envFile = './client/.env.production';

    file_put_contents($envFile, "VITE_API_BASE_URL=$defaultInstance\n");
    run('yarn run tsc -b && yarn run vite build', context: context()->withWorkingDirectory('./client'));
    unlink($envFile);

    $toDirectory = rtrim($toDirectory, '/');
    run("rm -rfv $toDirectory/*");
    run("cp -rfv ./client/dist/* $toDirectory/");
}

#[AsTask(description: 'Lint code')]
function lint(): void
{
    run('yarn run eslint .', context: context()->withWorkingDirectory('./client'));
}
