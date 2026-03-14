<?php

namespace qa;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\io;
use function Castor\run;
use function Castor\variable;
use function client\build as client_build;
use function client\install as client_install;
use function docker\docker_compose_run;
use function docker\docker_exit_code;
use function extension\build as extension_build;
use function extension\install as extension_install;

#[AsTask(description: 'Runs all QA tasks')]
function all(): int
{
    $cs = cs();
    $phpstan = phpstan();
    $twigCs = twigCs();
    $phpunit = phpunit();
    $client = client();
    $extension = extension();
    $docs = docs();

    return max($cs, $phpstan, $twigCs, $phpunit, $client, $extension, $docs);
}

#[AsTask(description: 'All server related QA tasks')]
function server(): int
{
    $cs = cs();
    $phpstan = phpstan();
    $twigCs = twigCs();
    $phpunit = phpunit();

    return max($cs, $phpstan, $twigCs, $phpunit);
}

#[AsTask(description: 'Client lint + build')]
function client(): int
{
    client_install();
    $lint = run('castor client:lint', context: context()->withAllowFailure())->getExitCode();
    if (0 !== $lint) {
        return $lint;
    }

    try {
        client_build(verifyOnly: true);
    } catch (\Throwable) {
        return 1;
    }

    return 0;
}

#[AsTask(description: 'Extension lint + build')]
function extension(): int
{
    extension_install();
    $lint = run('castor extension:lint', context: context()->withAllowFailure())->getExitCode();
    if (0 !== $lint) {
        return $lint;
    }
    $typecheck = run('castor extension:typecheck', context: context()->withAllowFailure())->getExitCode();
    if (0 !== $typecheck) {
        return $typecheck;
    }

    try {
        extension_build();
    } catch (\Throwable) {
        return 1;
    }

    return 0;
}

#[AsTask(description: 'Docs build')]
function docs(): int
{
    io()->section('Building mdbook documentation...');

    return docker_exit_code('mdbook build', workDir: '/var/www/docs');
}

#[AsTask(description: 'Installs tooling')]
function install(): void
{
    io()->title('Installing QA tooling');

    docker_compose_run('composer install -o', workDir: '/var/www/tools/php-cs-fixer');
    docker_compose_run('composer install -o', workDir: '/var/www/tools/phpstan');
    docker_compose_run('composer install -o', workDir: '/var/www/tools/twig-cs-fixer');
}

#[AsTask(description: 'Updates tooling')]
function update(): void
{
    io()->title('Updating QA tooling');

    docker_compose_run('composer update -o', workDir: '/var/www/tools/php-cs-fixer');
    docker_compose_run('composer update -o', workDir: '/var/www/tools/phpstan');
    docker_compose_run('composer update -o', workDir: '/var/www/tools/twig-cs-fixer');
}

/**
 * @param string[] $rawTokens
 */
#[AsTask(description: 'Runs PHPUnit', aliases: ['phpunit'])]
function phpunit(#[AsRawTokens] array $rawTokens = []): int
{
    io()->section('Running PHPUnit...');

    fixtures();

    return docker_exit_code('bin/phpunit ' . implode(' ', $rawTokens));
}

#[AsTask(description: 'Loads test related fixtures')]
function fixtures(): void
{
    run('castor server:fixtures --context test');
    run('castor server:fixtures --context test_ap_server');
}

#[AsTask(description: 'Runs PHPStan', aliases: ['phpstan'])]
function phpstan(
    #[AsOption(description: 'Generate baseline file', shortcut: 'b')]
    bool $baseline = false,
): int {
    if (!is_dir(variable('root_dir') . '/tools/phpstan/vendor')) {
        install();
    }

    io()->section('Running PHPStan...');

    $options = $baseline ? '--generate-baseline --allow-empty-baseline' : '';
    $command = \sprintf('phpstan analyse --memory-limit=-1 %s -v', $options);

    return docker_exit_code($command);
}

#[AsTask(description: 'Fixes Coding Style', aliases: ['cs'])]
function cs(bool $dryRun = false): int
{
    if (!is_dir(variable('root_dir') . '/tools/php-cs-fixer/vendor')) {
        install();
    }

    io()->section('Running PHP CS Fixer...');

    if ($dryRun) {
        return docker_exit_code('php-cs-fixer fix --dry-run --diff');
    }

    return docker_exit_code('php-cs-fixer fix');
}

#[AsTask(description: 'Fixes Twig Coding Style', aliases: ['twig-cs'])]
function twigCs(bool $dryRun = false): int
{
    if (!is_dir(variable('root_dir') . '/tools/twig-cs-fixer/vendor')) {
        install();
    }

    io()->section('Running Twig CS Fixer...');

    if ($dryRun) {
        return docker_exit_code('twig-cs-fixer');
    }

    return docker_exit_code('twig-cs-fixer --fix');
}
