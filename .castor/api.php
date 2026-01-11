<?php /** @noinspection PhpUnused */

namespace api;

use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;
use Castor\Attribute\AsOption;

use function Castor\context;
use function Castor\io;
use function Castor\notify;
use function Castor\variable;
use function docker\build;
use function docker\docker_compose_run;
use function docker\up;

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
        'extra_domains' => [],
        'php_version' => '8.4',
        'registry' => $_SERVER['DS_REGISTRY'] ?? null,
    ];
}

#[AsTask(description: 'Builds and starts the infrastructure, then install the api (composer, yarn, ...)')]
function start(): void
{
    io()->title('Starting the stack');

    build();
    install();
    up(profiles: ['default']);
    migrate();

    notify('The stack is now up and running.');
    io()->success('The stack is now up and running.');
}

#[AsTask(description: 'Installs the api (composer, yarn, ...)')]
function install(): void
{
    io()->title("Installing the API");

    $basePath = sprintf("%s/api", variable('root_dir'));

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

    \qa\install();
}

#[AsTask(description: 'Migrates database schema', namespace: 'api:db', aliases: ['migrate'])]
function migrate(): void
{
    io()->title('Migrating the database schema');

    docker_compose_run('bin/console doctrine:database:create --if-not-exists');
    docker_compose_run('bin/console doctrine:migration:migrate -n --allow-no-migration --all-or-nothing');
}

#[AsTask(description: 'Loads fixtures', namespace: 'api:db', aliases: ['fixtures'])]
function fixtures(): void
{
    io()->title('Loads fixtures');

    docker_compose_run('bin/console doctrine:database:drop --force');
    migrate();
    docker_compose_run('bin/console foundry:load-stories -n --append');
}

#[AsTask(description: 'Start all messenger consumers', aliases: ['consume'])]
function consume_messages(#[AsOption] int $limit = 100): void
{
    docker_compose_run("bin/console messenger:consume async -vvv --memory-limit=512M --time-limit=3600 --limit=$limit");
}

#[AsTask(description: 'Update the openapi definition file', aliases: ['openapi'])]
function openapi(): void
{
    docker_compose_run("./vendor/bin/openapi src/Entity src/Controller --format json --output openapi.json");
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

    $c = context();
    if ($params[0] === '--no-it') {
        array_shift($params);
    } else {
        $c = $c->toInteractive();
    }

    docker_compose_run(implode(' ', $params), c: $c->withEnvironment($_ENV + $_SERVER));
}

/**
 * @param array<mixed> $params
 */
#[AsTask(description: 'Console command called in the builder', aliases: ['bin/console', 'console'])]
function console(#[AsRawTokens] array $params = []): void
{
    docker_compose_run('bin/console ' . implode(' ', $params));
}
