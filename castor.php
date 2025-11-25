<?php

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\guard_min_version;
use function Castor\import;
use function Castor\io;
use function Castor\notify;
use function Castor\variable;
use function docker\about;
use function docker\build;
use function docker\docker_compose_run;
use function docker\up;

// use function docker\workers_start;
// use function docker\workers_stop;

guard_min_version('0.26.0');

import(__DIR__ . '/.castor');

/**
 * @return array{project_name: string, root_domain: string, extra_domains: string[], php_version: string}
 */
function create_default_variables(): array
{
    $projectName = 'bookmarkhive';
    $tld = 'test';

    return [
        'project_name' => $projectName,
        'root_domain' => "{$projectName}.{$tld}",
        'extra_domains' => [
            "api.{$projectName}.{$tld}",
            "admin.{$projectName}.{$tld}",
        ],
        // In order to test docker stater, we need a way to pass different values.
        // You should remove the `$_SERVER` and hardcode your configuration.
        'php_version' => '8.4',
        'registry' => $_SERVER['DS_REGISTRY'] ?? null,
    ];
}

#[AsTask(description: 'Builds and starts the infrastructure, then install the application (composer, yarn, ...)')]
function start(): void
{
    io()->title('Starting the stack');

    // workers_stop();
    build();
    install();
    up(profiles: ['default']); // We can't start worker now, they are not installed
    migrate();
    // workers_start();

    notify('The stack is now up and running.');
    io()->success('The stack is now up and running.');

    about();
}

#[AsTask(description: 'Installs the application (composer, yarn, ...)', namespace: 'app', aliases: ['install'])]
function install(): void
{
    io()->title('Installing the application');

    $basePath = sprintf('%s/application', variable('root_dir'));

    if (is_file("{$basePath}/composer.json")) {
        io()->section('Installing PHP dependencies');
        docker_compose_run('composer install -n --prefer-dist --optimize-autoloader');
    }
    if (is_file("{$basePath}/yarn.lock")) {
        io()->section('Installing Node.js dependencies');
        docker_compose_run('yarn install --frozen-lockfile');
    } elseif (is_file("{$basePath}/package.json")) {
        io()->section('Installing Node.js dependencies');

        if (is_file("{$basePath}/package-lock.json")) {
            docker_compose_run('npm ci');
        } else {
            docker_compose_run('npm install');
        }
    }
    if (is_file("{$basePath}/importmap.php")) {
        io()->section('Installing importmap');
        docker_compose_run('bin/console importmap:install');
    }

    docker_compose_run('bin/console lexik:jwt:generate-keypair --skip-if-exists');

    qa\install();
}

#[AsTask(description: 'Clears the application cache', namespace: 'app', aliases: ['cache-clear'])]
function cache_clear(bool $warm = true): void
{
    io()->title('Clearing the application cache');

    docker_compose_run('rm -rf var/cache/');

    if ($warm) {
        cache_warmup();
    }
}

#[AsTask(description: 'Warms the application cache', namespace: 'app', aliases: ['cache-warmup'])]
function cache_warmup(): void
{
    io()->title('Warming the application cache');

    docker_compose_run('bin/console cache:warmup', c: context()->withAllowFailure());
}

#[AsTask(description: 'Migrates database schema', namespace: 'app:db', aliases: ['migrate'])]
function migrate(): void
{
    io()->title('Migrating the database schema');

    docker_compose_run('bin/console doctrine:database:create --if-not-exists');
    docker_compose_run('bin/console doctrine:migration:migrate -n --allow-no-migration --all-or-nothing');
}

#[AsTask(description: 'Loads fixtures', namespace: 'app:db', aliases: ['fixtures'])]
function fixtures(): void
{
    io()->title('Loads fixtures');

    docker_compose_run('bin/console foundry:load-stories -n');
}

/**
 * @param array<mixed> $params
 */
#[AsTask(description: 'Opens a shell (bash) into a builder container', aliases: ['builder'])]
function builder(#[AsRawTokens] array $params = ['bash']): void
{
    if (0 === count($params)) {
        $params = ['bash'];
    }

    $c = context()
        ->toInteractive()
        ->withEnvironment($_ENV + $_SERVER)
    ;

    docker_compose_run(implode(' ', $params), c: $c);
}

/**
 * @param array<mixed> $params
 */
#[AsTask(namespace: 'proxy', description: 'Composer command called in the builder', aliases: ['composer'])]
function composer(#[AsRawTokens] array $params = []): void
{
    docker_compose_run('composer ' . implode(' ', $params));
}

/**
 * @param array<mixed> $params
 */
#[AsTask(namespace: 'proxy', description: 'Console command called in the builder', aliases: ['bin/console', 'console'])]
function console(#[AsRawTokens] array $params = []): void
{
    docker_compose_run('bin/console ' . implode(' ', $params));
}

/**
 * @param array<mixed> $params
 */
#[AsTask(namespace: 'proxy', description: 'Yarn command called in the builder', aliases: ['yarn'])]
function yarn(#[AsRawTokens] array $params = []): void
{
    docker_compose_run('yarn ' . implode(' ', $params));
}
