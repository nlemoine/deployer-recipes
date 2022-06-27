<?php declare(strict_types=1);

namespace HelloNico\Deployer;

use function Deployer\runLocally;
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
    $path = runLocally("command -v $nameEscaped || which $nameEscaped || type -p $nameEscaped");
    if (empty($path)) {
        throw new \RuntimeException("Can't locate [$nameEscaped] - neither of [command|which|type] commands are available");
    }

    // Deal with issue when `type -p` outputs something like `type -ap` in some implementations
    return trim(str_replace("$name is", "", $path));
}

// Add include path
if (php_sapi_name() === 'cli' && isset($_SERVER['SCRIPT_NAME']) && basename($_SERVER['SCRIPT_NAME']) === 'dep') {
    set_include_path(dirname(realpath(__DIR__ . '/../../../autoload.php')) . PATH_SEPARATOR . get_include_path());
}
