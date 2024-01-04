<?php

namespace Deployer;

require_once __DIR__ . '/deploy/wp-cli.php';

set('home_url', function () {
    $homeUrl = wp('option get home');
    if (empty($homeUrl)) {
        $config = wpGetConfig();
        $homeUrl = $config['WP_HOME'] ?? null;
    }

    return $homeUrl;
});

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
