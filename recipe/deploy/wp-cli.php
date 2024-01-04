<?php

declare(strict_types=1);

namespace Deployer;

// Self update wp-cli from time to time
// Set `false` to disable or an integer to update every N days
// Default to 30 days
set('wpcli_self_update', 30);
set('wpcli_force_phar', true);

desc('Install WP-CLI');
set('bin/wp', function () {
    $binPath = '{{deploy_path}}/.dep/wp-cli.phar';
    if (test("[ -f {$binPath} ]")) {
        // If wp-cli.phar is older than `wpcli_self_update` days, run self update
        if (
            get('wpcli_self_update', 0)
            && strtotime(sprintf('+%d days', (int) get('wpcli_self_update')), (int) run("stat -c %Y {$binPath}")) <= time()
        ) {
            warning('WP CLI is older than {{wpcli_self_update}} days, updating wp-cli...');
            run("{{bin/php}} {$binPath} cli update --yes");
            // Avoid running update on each deploy
            run("touch -m $(date +%s) {$binPath}");
        }

        return "{{bin/php}} {$binPath}";
    }

    if (commandExist('wp') && !get('wpcli_force_phar')) {
        return which('wp');
    }

    warning("WP CLI binary wasn't found. Installing latest WP CLI to {$binPath}.");
    run('cd {{deploy_path}} && curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar');
    run("mv {{deploy_path}}/wp-cli.phar {$binPath}");
    return "{{bin/php}} {$binPath}";
});

/**
 * Runs wp-cli subcommand.
 *
 * @param string $command Subcommand with all arguments
 *
 * @return string
 */
function wp($command)
{
    cd('{{release_or_current_path}}');

    return run('{{bin/wp}} ' . $command);
}

/**
 * Returns wp-cli subcommand status.
 *
 * @param string $command Subcommand with all arguments
 *
 * @return bool
 */
function wpTest($command)
{
    cd('{{release_or_current_path}}');

    return test('{{bin/wp}} ' . $command);
}

/**
 * Checks whether WordPress core installed.
 *
 * @param bool $refresh Refresh
 *
 * @return bool
 */
function wpIsCoreInstalled($refresh = false)
{
    if ($refresh || !has('wp_core_installed')) {
        wpRefreshCoreInstalled();
    }

    return get('wp_core_installed');
}

/**
 * Refreshes whether WordPress core installed.
 */
function wpRefreshCoreInstalled()
{
    set('wp_core_installed', function () {
        return wpTest('core is-installed');
    });
}

/**
 * Fetches WordPress config.
 *
 * @return array
 */
function wpFetchConfig()
{
    $config = [];
    $data = json_decode(wp('config list --json'), true);

    foreach ($data as $value) {
        if ($value['type'] === 'constant') {
            $config[$value['name']] = $value['value'];
        }
    }

    return $config;
}

/**
 * Refreshes WordPress config.
 */
function wpRefreshConfig()
{
    set('wp_config', function () {
        return wpFetchConfig();
    });
}

/**
 * Returns WordPress config.
 *
 * @param bool $refresh (optional) Refresh
 *
 * @return array
 */
function wpGetConfig($refresh = false)
{
    if ($refresh || !has('wp_config')) {
        wpRefreshConfig();
    }

    return get('wp_config');
}

/**
 * Fetches WordPress plugins list.
 *
 * @return array
 */
function wpFetchPlugins()
{
    $list = json_decode(wp('plugin list --json'), true);

    $plugins = [];
    foreach ($list as $plugin) {
        $plugins[$plugin['name']] = $plugin;
    }

    return $plugins;
}

/**
 * Refreshes WordPress plugins list.
 */
function wpRefreshPlugins()
{
    set('wp_plugins', function () {
        return wpFetchPlugins();
    });
}

/**
 * Returns installed WordPress plugins.
 *
 * @param bool $refresh (optional) Refresh plugin list
 *
 * @return array
 */
function wpGetPlugins($refresh = false)
{
    if ($refresh || !has('wp_plugins')) {
        wpRefreshPlugins();
    }

    return get('wp_plugins');
}

/**
 * Returns WordPress plugin status.
 *
 * @param string $plugin  Plugin name
 * @param bool   $refresh Refresh plugin list
 *
 * @return string Plugin status (e.g. 'active', 'not-installed')
 */
function wpGetPluginStatus($plugin, $refresh = false)
{
    $plugins = wpGetPlugins($refresh);

    return $plugins[$plugin]['status'] ?? 'not-installed';
}

/**
 * Checks whether plugin is acvive.
 *
 * @param string $plugin  Plugin name (e.g. 'woocommerce')
 * @param bool   $refresh Refresh plugins list
 *
 * @return bool
 */
function wpIsPluginActive($plugin, $refresh = false)
{
    return wpGetPluginStatus($plugin, $refresh) === 'active';
}
