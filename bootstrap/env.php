<?php
declare(strict_types=1);

$envFile = dirname(__DIR__) . '/.env';

if (!is_file($envFile) || !is_readable($envFile)) {
    return;
}

$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($lines === false) {
    return;
}

foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }

    [$name, $value] = array_pad(explode('=', $line, 2), 2, '');
    $name = trim($name);
    $value = trim($value);

    if ($name === '') {
        continue;
    }

    if (
        (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
        (str_starts_with($value, '\'') && str_ends_with($value, '\''))
    ) {
        $value = substr($value, 1, -1);
    }

    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}
