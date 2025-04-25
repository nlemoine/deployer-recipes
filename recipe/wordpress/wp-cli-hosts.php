<?php

namespace Deployer;

use Deployer\Host\Host;
use Symfony\Component\Yaml\Yaml;

/**
 * Parses a SSH URL and returns the components.
 *
 * @see https://github.com/wp-cli/wp-cli/blob/43f1d7bee4ea217d6a7a89daaa204c0e9a287882/php/utils.php#L1138-L1190
 *
 * @param integer $component
 */
function parseSshUrl(string $url, int $component = -1): array|string|int|null
{
    preg_match('#^((docker|docker\-compose|docker\-compose\-run|ssh|vagrant):)?(([^@:]+)@)?([^:/~]+)(:([\d]*))?((/|~)(.+))?$#', $url, $matches);
    $bits = [];
    foreach (
        [
            2 => 'scheme',
            4 => 'user',
            5 => 'host',
            7 => 'port',
            8 => 'path',
        ] as $i => $key
    ) {
        if (!empty($matches[$i])) {
            $bits[$key] = $matches[$i];
        }
    }

    // Find the hostname from `vagrant ssh-config` automatically.
    if (preg_match('/^vagrant:?/', $url)) {
        if ($bits['host'] === 'vagrant' && empty($bits['scheme'])) {
            $bits['scheme'] = 'vagrant';
            $bits['host'] = '';
        }
    }

    switch ($component) {
        case PHP_URL_SCHEME:
            return isset($bits['scheme']) ? $bits['scheme'] : null;
        case PHP_URL_USER:
            return isset($bits['user']) ? $bits['user'] : null;
        case PHP_URL_HOST:
            return isset($bits['host']) ? $bits['host'] : null;
        case PHP_URL_PATH:
            return isset($bits['path']) ? $bits['path'] : null;
        case PHP_URL_PORT:
            return isset($bits['port']) ? (int) $bits['port'] : null;
        default:
            return $bits;
    }
}

/**
 * Get hosts from wp-cli.yml aliases.
 *
 * @return Host[]
 */
function getHostsFromWpCliAliases(string $wpCliFile): array
{
    $wpCliConfig = Yaml::parseFile($wpCliFile);
    $aliases = array_filter($wpCliConfig, fn ($v, $key): bool => isset($v['ssh']) && str_starts_with($key, '@'), ARRAY_FILTER_USE_BOTH);
    if (\count($aliases) === 0) {
        throw new \RuntimeException('No aliases found in wp-cli.yml');
    }

    $currentFolder = 'current';

    $hosts = [];
    foreach ($aliases as $stage => $alias) {
        $sshParts = parseSshUrl($alias['ssh']);
        if (empty($sshParts['host'])) {
            throw new \RuntimeException(\sprintf('No host found in ssh url: %s', $alias['ssh']));
        }

        $host = new Host($sshParts['host']);
        $host->setHostname($sshParts['host']);
        $host->setRemoteUser($sshParts['user'] ?? null);
        if (isset($sshParts['port'])) {
            $host->setPort((int) $sshParts['port']);
        }

        $deployPath = rtrim($sshParts['path'], '/');
        if (str_ends_with($deployPath, $currentFolder)) {
            $deployPath = substr($deployPath, 0, \strlen($currentFolder) * -1);
        }
        $host->setDeployPath(rtrim($deployPath, '/'));

        $host->setLabels([
            'stage' => ltrim($stage, '@'),
        ]);

        foreach ($alias['deploy'] ?? [] as $key => $value) {
            if (\is_array($value)) {
                $host->set($key, $value);
            } else {
                $host->set($key, (string) $value);
            }
        }

        $hosts[] = $host;
    }

    return $hosts;
}

(function () {
    global $lookUp;
    $wpCliFile = $lookUp('wp-cli.yml');
    if (!file_exists($wpCliFile)) {
        throw new \RuntimeException('wp-cli.yml was not found');
    }

	// Auto configure hosts from wp-cli.yml
    $hosts = getHostsFromWpCliAliases($wpCliFile);
    foreach ($hosts as $key => $host) {
        Deployer::get()->hosts->set($host->getHostname(), $host);
    }
})();
