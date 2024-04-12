<?php

namespace Deployer;

require_once __DIR__ . '/deploy/wp-cli.php';

set('wordpress_home_url', function () {
    $homeUrl = wp('option get home');
    if (empty($homeUrl)) {
        $config = wpGetConfig();
        $homeUrl = $config['WP_HOME'] ?? null;
    }

    return $homeUrl;
});

# WordPress cron
set('bin/wordpress_cron', function () {
    return str_replace(
        get('release_or_current_path'),
        get('current_path'),
        parse('{{bin/wp}} cron event run --due-now'),
    );
});
set('wordpress_cron_interval', '*/15 * * * *');
set('wordpress_cron_job', '{{wordpress_cron_interval}} cd {{current_path}} && {{bin/wordpress_cron}} > /dev/null 2>&1');

function wordpressSkipIfNotInstalled()
{
    if (!wpIsCoreInstalled()) {
        info('Skip: WordPress is not installed.');

        return true;
    }

    return false;
}

desc('WordPress: migrate database');
task('wordpress:db:migrate', function () {
    if (wordpressSkipIfNotInstalled()) {
        return;
    }

    wp('core update-db');
});

desc('WordPress: flush cache');
task('wordpress:cache:flush', function () {
    if (wordpressSkipIfNotInstalled()) {
        return;
    }

    wp('cache flush');
});

desc('WordPress: flush rewrite rules');
task('wordpress:rewrite:flush', function () {
    if (wordpressSkipIfNotInstalled()) {
        return;
    }

    wp('rewrite flush');
});

desc('WordPress: check installation');
task('wordpress:check', function () {
    wp("eval 'echo 200;'");
});

desc('WordPress: custom WP-CLI commands');
task('wordpress:commands', function () {
    if (wordpressSkipIfNotInstalled()) {
        return;
    }

    $commands = get('wpcli_commands', []);
    foreach ($commands as $command) {
        wp($command);
    }
});
