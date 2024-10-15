<?php

declare(strict_types=1);

namespace Deployer;

use Deployer\Exception\RunException;

/**
 * @throws RunException
 */
function whichLocally(string $name): string
{
    $nameEscaped = escapeshellarg($name);

    // Try `command`, should cover all Bourne-like shells
    // Try `which`, should cover most other cases
    // Fallback to `type` command, if the rest fails
    $path = runLocally("command -v {$nameEscaped} || which {$nameEscaped} || type -p {$nameEscaped}");
    if (empty($path)) {
        throw new \RuntimeException("Can't locate [{$nameEscaped}] - neither of [command|which|type] commands are available");
    }

    // Deal with issue when `type -p` outputs something like `type -ap` in some implementations
    return trim(str_replace("{$name} is", '', $path));
}

/**
 * Get file modification time.
 *
 * Stat behaves differently on different systems.
 *
 * @see https://unix.stackexchange.com/questions/349555/stat-modification-timestamp-of-a-file
 *
 * @param string $path
 * @return integer Unix timestamp
 */
function getModifiedTime(string $path): int
{
    $statArgs = '-c %Y';
    if (str_contains(strtolower(run("uname")), 'freebsd')) {
        $statArgs = '-f %m';
    }

    return (int) run("stat {$statArgs} {$path}");
}
