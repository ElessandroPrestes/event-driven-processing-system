<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$pestBinary = $projectRoot.'/vendor/bin/pest';
$arguments = array_slice($_SERVER['argv'], 1);

if (! file_exists($pestBinary)) {
    fwrite(STDERR, "Pest nao encontrado. Execute 'composer install' antes de rodar a cobertura.\n");

    exit(1);
}

$hasCoverageOutput = in_array('--coverage', $arguments, true);
$hasNoCoverage = in_array('--no-coverage', $arguments, true);

if (! $hasCoverageOutput && ! $hasNoCoverage) {
    array_unshift($arguments, '--coverage');
}

if (! in_array('--colors=always', $arguments, true)) {
    $arguments[] = '--colors=always';
}

$command = [PHP_BINARY];

if (extension_loaded('pcov')) {
    $command[] = '-d';
    $command[] = 'pcov.enabled=1';
} elseif (extension_loaded('xdebug')) {
    putenv('XDEBUG_MODE=coverage');
    $_ENV['XDEBUG_MODE'] = 'coverage';
    $_SERVER['XDEBUG_MODE'] = 'coverage';
} else {
    fwrite(STDERR, "Nenhum driver de cobertura disponivel. Instale pcov/xdebug ou rode no container/CI do projeto.\n");

    exit(1);
}

$command[] = $pestBinary;

foreach ($arguments as $argument) {
    $command[] = $argument;
}

$escapedCommand = implode(' ', array_map(
    static fn (string $part): string => escapeshellarg($part),
    $command,
));

passthru($escapedCommand, $exitCode);

exit($exitCode);
