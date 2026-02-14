<?php

/** @noinspection PhpUnused */

namespace docs;

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\run;
use function docker\docker_compose_run;
use function server\openapi;

#[AsTask(description: 'Serve and watch the mdbook documentation', aliases: ['docs:start'])]
function watch(): void
{
    io()->title('Serving and watching mdbook documentation');

    docker_compose_run('mdbook serve -n 0.0.0.0 -p 3000', workDir: '/var/www/docs', portMapping: true);
}

#[AsTask(description: 'Build the mdbook documentation')]
function build(#[AsArgument] $toDirectory = '../docs', #[AsOption] $withSwager = true): void
{
    io()->title('Building mdbook documentation');

    docker_compose_run('mdbook build', workDir: '/var/www/docs');

    $toDirectory = mb_rtrim($toDirectory, '/');
    run("rm -rfv {$toDirectory}/*");
    run("cp -rfv ./docs/book/* {$toDirectory}/");

    if ($withSwager) {
        openapi();
        run("mkdir {$toDirectory}/swagger && cp -rfv ./docs/swagger/* {$toDirectory}/swagger/");
    }
}
